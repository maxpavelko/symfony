<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests\Dbgp;

use Symfony\Component\Debug\Dbgp\DbgpServer;
use Symfony\Component\Debug\Dbgp\Breakpoint;

class DbgpServerTest extends \PHPUnit_Framework_TestCase
{
    protected $process;
    protected $socket;
    protected $pipes;
    protected $dbgp;

    public function setUp()
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('DBGP tests require Xdebug');
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('DBGP tests do not work on Windows');
        }

        if (!defined('PHP_BINARY')) {
            $this->markTestSkipped('PHP binary not found for DBGP tests');
        }

        if (!function_exists('proc_open')) {
            $this->markTestSkipped('DBGP tests require proc_open()');
        }

        $server = @stream_socket_server('tcp://0.0.0.0:9002', $errno, $errstr);

        if (!$server) {
            $this->markTestSkipped("DBGP server failed ($errno): $errstr");
        }

        $this->phpFile = dirname(__DIR__).'/Fixtures/dbgp-eval-loop.php';
        $env = array(
            'DBGP_COOKIE' => '123abc',
            'XDEBUG_CONFIG' => 'idekey=symfony remote_enable=1 remote_mode=req extended_info=1 remote_port=9002',
        );

        $this->process = proc_open(
            PHP_BINARY.' '.escapeshellarg($this->phpFile),
            array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
            $this->pipes,
            __DIR__,
            $env
        );

        try {
            $this->socket = @stream_socket_accept($server, 1);

            if (!$this->socket) {
                $this->markTestSkipped('DBGP socket accept failed');
            }

            $this->dbgp = new DbgpServer($this->socket);

            $this->assertSame($env['DBGP_COOKIE'], $this->dbgp->featureGet('session'));

            $x = array(
                'status' => 'break',
                'reason' => 'ok',
                'code' => 0,
                'filename' => 'file://'.$this->phpFile,
                'lineno' => 8,
                'exception' => '',
                'message' => '',
            );
            $this->dbgp->run();
        } catch (\Exception $e) {
            proc_terminate($this->process);

            throw $e;
        }
    }

    public function tearDown()
    {
        fclose($this->socket);
        proc_close($this->process);
    }

    public function testDbgpServer()
    {
        $dbgp = $this->dbgp;
        $dbgp->stdout($dbgp::STREAM_REDIRECT);
        $dbgp->propertySet('$code', 'echo "A";');
        $dbgp->run();

        $this->assertSame('A', $dbgp->readStdout());

        $dbgp->propertySet('$code', '');
        $r = $dbgp->propertyGet('$code');
        $this->assertSame('string', $r['type']);
        $this->assertSame('', $r['value']);

        $dbgp->propertySet('$code', 'return 123;');
        $dbgp->run();
        $r = $dbgp->propertyGet('$result');
        $this->assertEquals(123, $r['value']);

        $dbgp->run();
        $this->assertSame('stopping', $dbgp->status());
    }
}
