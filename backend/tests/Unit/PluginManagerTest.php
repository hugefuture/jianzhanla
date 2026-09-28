<?php
declare(strict_types=1);

namespace tests\Unit;

use app\common\plugin\AbstractPlugin;
use app\common\plugin\Hook;
use app\common\plugin\PluginManager;
use PHPUnit\Framework\TestCase;

/**
 * 插件内核（DB-free）：清单解析、命名空间映射、钩子调度
 *
 * 说明：normalize*() 是 protected，这里用可测试子类暴露，不改动被测类可见性。
 */
class PluginManagerTest extends TestCase
{
    private TestablePluginManager $manager;

    protected function setUp(): void
    {
        $this->manager = new TestablePluginManager();
        Hook::getInstance()->clear();
    }

    protected function tearDown(): void
    {
        Hook::getInstance()->clear();
    }

    // ---------- 清单解析 ----------

    public function testManifestOfBuiltinHelloIsParsed(): void
    {
        $m = $this->manager->manifest('hello');
        $this->assertIsArray($m);
        $this->assertSame('hello', $m['name']);
        $this->assertSame('Hello 示例插件', $m['title']);
        $this->assertSame('1.0.0', $m['version']);
        $this->assertCount(2, $m['routes'], '两条示例接口');
        $this->assertCount(1, $m['menus']);
        $this->assertSame('plugin:hello:index', $m['menus'][0]['perms']);
    }

    public function testManifestOfHyphenDirectoryIsParsed(): void
    {
        $m = $this->manager->manifest('login-notify');
        $this->assertIsArray($m);
        $this->assertSame('登录动态记录', $m['title']);
        $this->assertContains('user.login.success', $m['hooks']);
    }

    public function testIllegalNameIsRejected(): void
    {
        $this->assertNull($this->manager->manifest('../evil'), '目录穿越必须拒绝');
        $this->assertNull($this->manager->manifest('Not-Exists'), '不存在的插件返回 null');
        $this->assertNull($this->manager->manifest('1abc'), '数字开头非法');
    }

    // ---------- 命名空间 ----------

    public function testHyphenNameMapsToUnderscoreNamespace(): void
    {
        $this->assertSame('plugin\\login_notify', $this->manager->namespace('login-notify'));
        $this->assertSame('plugin\\hello', $this->manager->namespace('hello'));
    }

    public function testPluginMainClassCanBeLoaded(): void
    {
        $plugin = $this->manager->load('hello');
        $this->assertInstanceOf(AbstractPlugin::class, $plugin);
        $this->assertSame('hello', $plugin->name());

        $hyphen = $this->manager->load('login-notify');
        $this->assertInstanceOf(AbstractPlugin::class, $hyphen, '连字符目录也要能加载主类');
    }

    public function testUnknownPluginLoadsNothing(): void
    {
        $this->assertNull($this->manager->load('not-exists'));
    }

    // ---------- 清单规范化 ----------

    public function testRoutesAreNormalizedAndInvalidDropped(): void
    {
        $routes = $this->manager->exposeNormalizeRoutes([
            ['method' => 'POST', 'rule' => '/stats/', 'action' => 'Index/stats'],
            ['rule' => 'no-action'],
            'invalid',
        ]);
        $this->assertCount(1, $routes);
        $this->assertSame('post', $routes[0]['method'], '方法统一小写');
        $this->assertSame('stats', $routes[0]['rule'], '斜杠被去掉，避免路由拼出双斜杠');
    }

    public function testUnknownConfigTypeFallsBackToText(): void
    {
        $config = $this->manager->exposeNormalizeConfig([
            ['key' => 'a', 'type' => 'weird'],
            ['key' => 'b', 'type' => 'switch', 'default' => 1],
            ['no-key' => true],
        ]);
        $this->assertCount(2, $config, '缺 key 的项应被丢弃');
        $this->assertSame('text', $config[0]['type']);
        $this->assertSame('switch', $config[1]['type']);
    }

    public function testConfigDefaultsComeFromSchema(): void
    {
        $defaults = $this->manager->configDefaults($this->manager->manifest('hello'));
        $this->assertSame('blue', $defaults['theme']);
        $this->assertSame(1, $defaults['show_time']);
        $this->assertArrayHasKey('greeting', $defaults);
    }

    public function testDiscoverContainsBuiltinPlugins(): void
    {
        $list = $this->manager->scan();
        $this->assertArrayHasKey('hello', $list);
        $this->assertArrayHasKey('login-notify', $list);
    }

    // ---------- 钩子 ----------

    public function testHookRunsInPriorityOrder(): void
    {
        $hook = Hook::getInstance();
        $trace = [];
        $hook->listen('demo.event', function () use (&$trace) {
            $trace[] = 'low';
            return null;
        }, 1);
        $hook->listen('demo.event', function () use (&$trace) {
            $trace[] = 'high';
            return null;
        }, 10);
        $hook->trigger('demo.event');
        $this->assertSame(['high', 'low'], $trace);
    }

    public function testHookChainsPayloadWhenListenerReturnsValue(): void
    {
        $hook = Hook::getInstance();
        $hook->listen('demo.chain', fn ($payload) => $payload . '-a');
        $hook->listen('demo.chain', fn ($payload) => $payload . '-b');
        $hook->listen('demo.chain', fn () => null, -1);   // 返回 null 不改写
        $this->assertSame('seed-a-b', $hook->trigger('demo.chain', 'seed'));
    }

    public function testListenerExceptionDoesNotBreakFlow(): void
    {
        $hook = Hook::getInstance();
        $reached = false;
        $hook->listen('demo.risky', function () {
            throw new \RuntimeException('插件炸了');
        }, 5);
        $hook->listen('demo.risky', function () use (&$reached) {
            $reached = true;
            return null;
        }, 1);
        $this->assertSame('keep', $hook->trigger('demo.risky', 'keep'));
        $this->assertTrue($reached, '单个插件异常不能影响主流程');
    }

    public function testUnregisteredEventReturnsPayloadAsIs(): void
    {
        $hook = Hook::getInstance();
        $this->assertFalse($hook->has('nobody.listens'));
        $this->assertSame('raw', $hook->trigger('nobody.listens', 'raw'));
    }

    public function testEventsAreListable(): void
    {
        $hook = Hook::getInstance();
        $hook->listen('demo.one', fn () => null);
        $hook->listen('demo.two', fn () => null);
        $this->assertSame(['demo.one', 'demo.two'], $hook->events());
        $this->assertSame(1, $hook->count('demo.one'));
    }

    public function testPluginRegisterHooksItself(): void
    {
        // 模拟一次请求内的插件启动（不查库，直接对主类调用 register）
        $plugin = $this->manager->load('login-notify');
        $this->assertNotNull($plugin);
        $plugin->register();
        $this->assertTrue(Hook::getInstance()->has('user.login.success'));
        $this->assertTrue(Hook::getInstance()->has('user.login.fail'));
    }
}

/** 暴露 protected 方法的测试替身 */
class TestablePluginManager extends PluginManager
{
    public function exposeNormalizeRoutes(mixed $routes): array
    {
        return $this->normalizeRoutes($routes);
    }

    public function exposeNormalizeConfig(mixed $config): array
    {
        return $this->normalizeConfig($config);
    }
}
