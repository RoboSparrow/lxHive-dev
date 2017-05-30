<?php
namespace Tests\API;

use Tests\TestCase;

use API\Bootstrap;
use API\Config;
use API\AppInitException;

class BootstrapTest extends TestCase
{
    protected function setUp()
    {
        Bootstrap::factory(Bootstrap::None);
        Bootstrap::reset();
    }

    public function testConstructorIsNotPublic()
    {
        $this->expectException(\Error::class);
        $bootstrap = new Bootstrap(Bootstrap::None);
        $bootstrap = new Bootstrap(Bootstrap::Web);
    }

    ////
    // invalid modes
    ////

    public function testModeInvalid()
    {
        $this->expectException(AppInitException::class);
        $bootstrap = Bootstrap::factory(time());
        $bootstrap = Bootstrap::factory(-1);
        $bootstrap = Bootstrap::factory(\INF);
    }

    public function testModeFalseOrNull()
    {
        $bootstrap = Bootstrap::factory(false);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);

        $bootstrap = Bootstrap::factory(null);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);
    }

    ////
    // Bootstrap::None
    ////

    public function testModeNone()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::None);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);
    }

    public function testModeNoneMultiple()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::None);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);

        // 'Boostrap::factory(Bootstrap::None) can be called multiple times
        $bootstrap = Bootstrap::factory(Bootstrap::None);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);
    }

    public function testModeNoneDoesNotInitializeConfig()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::None);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);

        // 'Boostrap::factory(Bootstrap::None) does not initializes config singleton
        $this->expectException(AppInitException::class);
        Config::set('test_'.time(), 123);
    }

    ////
    // Bootstrap::None
    ////

    public function testModeWeb()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::Web);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::Web);
    }

    public function testModeWebSingleton()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::Web);
        $this->expectException(AppInitException::class);
        $bootstrap = Bootstrap::factory(Bootstrap::Web);
    }

    public function testModeWebInitializesConfig()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::Web);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::Web);

        // 'Boostrap::factory(Bootstrap::Web) initializes config singleton
        $now = time();
        Config::set('test_'.$now, $now);
        $this->assertEquals(Config::get('test_'.$now), $now);
    }

    ////
    // Bootstrap::None
    ////

    public function testModeTesting()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::Testing);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::Testing);
    }

    public function testModeTestingMultiple()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::Testing);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::Testing);

        // 'Boostrap::factory(Bootstrap::Testing) can be called multiple times
        $bootstrap = Bootstrap::factory(Bootstrap::None);
        $this->assertEquals(Bootstrap::mode(), Bootstrap::None);
    }

    public function testModeTestingCannotBeCalledAfterModeWeb()
    {
        $bootstrap = Bootstrap::factory(Bootstrap::Web);
        $this->expectException(AppInitException::class);
        // You need to reset Bootstrap!
        $bootstrap = Bootstrap::factory(Bootstrap::Testing);
    }
}