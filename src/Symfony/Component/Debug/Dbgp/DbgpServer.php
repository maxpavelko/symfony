<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Dbgp;

use DOMDocument;
use DOMElement;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\Dbgp\Exception\DbgpDebuggerException;
use Symfony\Component\Debug\Dbgp\Exception\DbgpStreamException;

/**
 * Partial DBGP protocol implementation for Xdebug interaction.
 *
 * @see http://xdebug.org/docs-dbgp.php
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DbgpServer
{
    const RUN_RUN = 'run';
    const RUN_STEP_INTO = 'step_into';
    const RUN_STEP_OVER = 'step_over';
    const RUN_STEP_OUT = 'step_out';
    const RUN_STOP = 'stop';
    const RUN_DETACH = 'detach';

    const STATUS_STARTING = 'starting';
    const STATUS_RUNNING = 'running';
    const STATUS_STOPPED = 'stopped';
    const STATUS_BREAK = 'break';
    const STATUS_STOPPING = 'stopping';

    const STREAM_DISABLE = 0;
    const STREAM_COPY = 1;
    const STREAM_REDIRECT = 2;

    const REASON_OK = 'ok';
    const REASON_ERROR = 'error';
    const REASON_ABORTED = 'aborted';
    const REASON_EXCEPTION = 'exception';

    const PROPERTY_TYPE_EVAL = null;
    const PROPERTY_TYPE_SCALAR = 'scalar';
    const PROPERTY_TYPE_BOOL = 'bool';
    const PROPERTY_TYPE_INT = 'int';
    const PROPERTY_TYPE_FLOAT = 'float';
    const PROPERTY_TYPE_STRING = 'string';

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    private $socket;
    private $initPacket;
    private $transactionId = 0;
    private $status;
    private $reason;
    private $typeMap;
    private $stdoutData = false;
    private $breakpoints = array();
    private $breakpointsUpdateIds = array();

    private $features = array(
        'fileuri' => false,
        'language' => false,
        'protocol_version' => false,
        'appid' => false,
        'session' => false,
        'idekey' => false,
        'proxied' => false,
        'language_supports_threads' => null,
        'language_name' => null,
        'language_version' => null,
        'encoding' => null,
        'protocol_version' => null,
        'supports_async' => null,
        'data_encoding' => null,
        'breakpoint_languages' => null,
        'breakpoint_types' => null,
        'multiple_sessions' => null,
        'max_children' => null,
        'max_data' => null,
        'max_depth' => null,
        'supports_postmortem' => null,
        'show_hidden' => null,
        'notify_ok' => null,
        'detach' => null,
        'xcmd_profiler_name_get' => null,
        'xcmd_get_executable_lines' => null,
    );


    public function __construct($socket)
    {
        $this->socket = $socket;
        $this->initPacket = $this->read('init');

        if (false === $this->initPacket) {
            throw new DbgpStreamException('No init response from socket');
        }

        foreach ($this->features as $name => $value) {
            $this->features[$name] = null === $value
                ? $this->featureGet($name)
                : $this->initPacket->getAttribute($name);
        }
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getInitPacket()
    {
        return $this->initPacket;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function getFeatures()
    {
        return $this->features;
    }

    public function getBreakpoints()
    {
        return $this->breakpoints;
    }

    public function status()
    {
        $this->send('status');

        return $this->status;
    }

    public function featureGet($name)
    {
        if (!isset($this->features[$name])) {
            $x = $this->send('feature_get', array('-n' => $name));

            if ($x->getAttribute('supported')) {
                $this->features[$name] = $x->textContent;
            } else {
                $this->features[$name] = false;
            }
        }

        return $this->features[$name];
    }

    public function featureSet($name, $value)
    {
        $x = $this->send('feature_set', array('-n' => $name, '-v' => $value));

        if ($x->getAttribute('success')) {
            $this->features[$name] = $value;

            return true;
        }

        return false;
    }

    public function run($mode = self::RUN_RUN)
    {
        if (self::RUN_STOP !== $mode && self::RUN_DETACH !== $mode) {
            $this->updateBreakpoints();
        }

        $x = $this->send($mode);
        $x = $x->firstChild;

        $data = array(
            'status' => $this->status,
            'reason' => $this->reason,
        );

        if ($x instanceof DOMElement) {
            $data += array(
                'code' => (int) $x->getAttribute('code'),
                'filename' => $x->getAttribute('filename'),
                'lineno' => (int) $x->getAttribute('lineno'),
                'exception' => $x->getAttribute('exception'),
                'message' => $x->textContent,
            );
        }

        return $data;
    }

    public function stepInto()
    {
        return $this->run(self::RUN_STEP_INTO);
    }

    public function stepOver()
    {
        return $this->run(self::RUN_STEP_OVER);
    }

    public function stepOut()
    {
        return $this->run(self::RUN_STEP_OUT);
    }

    public function stop()
    {
        return $this->run(self::RUN_STOP);
    }

    public function detach()
    {
        return $this->run(self::RUN_DETACH);
    }

    public function breakpointSet(Breakpoint $bp)
    {
        $id = array_search($bp, $this->breakpoints, true);

        if (false === $id) {
            $args = array(
                '-t' => $bp->getType(),
                '-s' => $bp->getState(),
                '-f' => $bp->getFileName(),
                '-n' => $bp->getLineNo(),
                '-m' => $bp->getFunction(),
                '-x' => $bp->getException(),
                '-h' => $bp->getHitValue(),
                '-o' => $bp->getHitCondition(),
                '-r' => $bp->isTemporary(),
            );

            $x = $this->send('breakpoint_set', $args, $bp->getExpression());

            $id = $x->getAttribute('id');

            if ($id && $args['-r']) {
                $this->breakpoints[$id] = $bp;
                $this->breakpointsUpdateIds[$id] = $bp->getUpdateId();
            }
        }

        return $id;
    }

    public function breakpointGetHitCount(Breakpoint $bp)
    {
        $id = array_search($bp, $this->breakpoints, true);

        if (false !== $id) {
            $x = $this->send('breakpoint_get', array('-d' => $id));

            if ($x->firstChild) {
                return $x->firstChild->getAttribute('hit_count');
            }
        }
    }

    public function breakpointRemove(Breakpoint $bp)
    {
        $id = array_search($bp, $this->breakpoints, true);

        if (false !== $id) {
            unset($this->breakpoints[$id]);
            unset($this->breakpointsUpdateIds[$id]);
            $this->send('breakpoint_remove', array('-d' => $id));

            return true;
        }
    }

    public function stackDepth()
    {
        return (int) $this->send('stack_depth')->getAttribute('depth');
    }

    public function stackGet($depth = null)
    {
        $x = $this->send('stack_get', array('-d' => $depth));
        $s = array();

        foreach ($x->childNodes as $x) {
            $level = $x->getAttribute('level');

            $s[$level] = array(
                'type' => $x->getAttribute('type'),
                'filename' => $x->getAttribute('filename'),
                'lineno' => $x->getAttribute('lineno'),
                'where' => $x->getAttribute('where'),
                'cmdbegin' => $x->getAttribute('cmdbegin'),
                'cmdend' => $x->getAttribute('cmdend'),
            );
        }

        if (isset($depth)) {
            return $s[$depth];
        }

        return $s;
    }

    /**
     * The names of currently available contexts at a given stack depth.
     *
     * Xdebug has only 3 context names:
     * - 0: Locals
     * - 1: Superglobals
     * - 2: User defined constants
     */
    public function contextNames($depth = null)
    {
        $x = $this->send('context_names', array('-d' => $depth));
        $c = array();

        foreach ($x->childNodes as $x) {
            $c[$x->getAttribute('id')] = $x->getAttribute('name');
        }

        return $c;
    }

    public function contextGet($depth = null, $contextId = null)
    {
        $args = array(
            '-d' => $depth,
            '-c' => $contextId,
        );

        $x = $this->send('context_get', $args);
        $p = array();

        foreach ($x->childNodes as $x) {
            $p[] = $this->mapProperty($x);
        }

        return $p;
    }

    public function typemapGet()
    {
        isset($this->typeMap) or $this->typeMap = $this->send('typemap_get');

        return $this->typeMap;
    }

    public function propertyGet($fullname, $options = array())
    {
        $args = array('-n' => $fullname);
        isset($options['depth']) and $args['-d'] = $options['depth'];
        isset($options['contextId']) and $args['-c'] = $options['contextId'];
        isset($options['key']) and $args['-k'] = $options['key'];
        isset($options['maxSize']) and $args['-m'] = $options['maxSize'];
        isset($options['page']) and $args['-p'] = $options['page'];

        $x = $this->send('property_get', $args);

        return $this->mapProperty($x->firstChild ?: $x);
    }

    public function propertySet($fullname, $data, $type = self::PROPERTY_TYPE_SCALAR, $options = array())
    {
        if (self::PROPERTY_TYPE_SCALAR === $type) {
            if (is_bool($data)) {
                $type = self::PROPERTY_TYPE_BOOL;
            } elseif (is_string($data)) {
                $type = self::PROPERTY_TYPE_STRING;
            } elseif (!is_scalar($data)) {
                throw new DbgpDebuggerException(sprintf('Invalid $data argument: not a scalar (%s provided)', gettype($data)));
            }
        }

        $args = array('-n' => $fullname);
        isset($type) and $args['-t'] = $type;
        isset($options['depth']) and $args['-d'] = $options['depth'];
        isset($options['contextId']) and $args['-c'] = $options['contextId'];
        isset($options['key']) and $args['-k'] = $options['key'];

        $x = $this->send('property_set', $args, $data);

        return (bool) $x->getAttribute('success');
    }

    public function propertyValue($fullname, $options = array())
    {
        $args = array('-n' => $fullname);
        isset($options['depth']) and $args['-d'] = $options['depth'];
        isset($options['contextId']) and $args['-c'] = $options['contextId'];
        isset($options['key']) and $args['-k'] = $options['key'];
        isset($options['maxSize']) and $args['-m'] = $options['maxSize'];
        isset($options['page']) and $args['-p'] = $options['page'];
        isset($options['address']) and $args['-a'] = $options['address'];

        $x = $this->send('property_value', $args);

        return $this->mapProperty($x);
    }

    public function source($file, $beginLine = null, $endLine = null)
    {
        $args = array(
            '-f' => $file,
            '-b' => $beginLine,
            '-e' => $endLine,
        );

        $x = $this->send('source', $args);

        return base64_decode($x->textContent);
    }

    public function stdout($mode)
    {
        $x = $this->send('stdout', array('-c' => $mode));

        return (bool) $x->getAttribute('success');
    }

    public function readStdout()
    {
        $data = $this->stdoutData;
        $this->stdoutData = false;

        return $data;
    }

    public function evalCode($code, $page = null)
    {
        $x = $this->send('eval', array('-p' => $page), $code);

        return $this->mapProperty($x->firstChild ?: $x);
    }

    public function xcmdProfilerNameGet()
    {
        try {
            $x = $this->send('xcmd_profiler_name_get');
        } catch (DbgpDebuggerException $x) {
            if (800 == $x->getCode()) {
                return false;
            } else {
                throw $x;
            }
        }

        return $x->textContent;
    }

    public function xcmdGetExecutableLines($depth)
    {
        $x = $this->send('xcmd_get_executable_lines', array('-d' => $depth));
        $lines = array();

        if ($x->firstChild) {
            foreach ($x->firstChild->childNodes as $x) {
                $line = (int) $x->getAttribute('lineno');

                if (isset($lines[$line])) {
                    ++$lines[$line];
                } else {
                    $lines[$line] = 1;
                }
            }
        }

        return $lines;
    }

    public function send($command, array $arguments = array(), $data = null)
    {
        if (isset($data) && !is_scalar($data)) {
            throw new DbgpDebuggerException(sprintf('Invalid $data argument: not a scalar (%s provided)', gettype($data)));
        }

        $cmd = $command.' -i '.++$this->transactionId;

        foreach ($arguments as $k => $v) {
            if (isset($v)) {
                if (!is_int($k)) {
                    $v = '"'.addslashes($v).'"';
                    $cmd .= " $k";
                }

                $cmd .= " $v";
            }
        }

        if (isset($this->logger)) {
            $k = strlen($data);
            $this->logger->debug($cmd.($k ? ' -- (+'.$k.' bytes)' : ''));
        }

        if (isset($data)) {
            $cmd .= ' -- '.(base64_encode($data) ?: '=');
        }

        stream_socket_sendto($this->socket, $cmd."\0");

        if (false === $dom = $this->read()) {
            $dom = new DOMDocument();
            $dom->loadXML('<response xmlns="urn:debugger_protocol_v1"/>');
            $dom = $dom->firstChild;
            $dom->setAttribute('command', $command);
            $dom->setAttribute('transaction_id', $this->transactionId);
        }

        return $dom;
    }

    protected function read($expectedTag = 'response')
    {
        for (;;) {
            $len = stream_get_line($this->socket, 20, "\0");

            if (! isset($len[0])) {
                return false;
            }

            if (!$len || $len !== (string) (int) $len) {
                throw new DbgpStreamException("Invalid DBGP stream: numerical data length expected");
            }

            $data = stream_get_line($this->socket, $len+1, "\0");

            $dom = new DOMDocument();
            $dom->loadXML($data, LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_NOWARNING | LIBXML_NOERROR);

            if (isset($this->logger)) {
                $this->logger->debug($dom->saveXML($dom->documentElement));
            }

            if ($dom->firstChild instanceof DOMElement) {
                $dom = $dom->firstChild;

                if ($this->handleResponse($expectedTag, $dom)) {
                    if ($dom->tagName === $expectedTag) {
                        return $dom;
                    }

                    continue;
                }
            }

            throw new DbgpStreamException('Invalid DBGP response: '.$data);
        }
    }

    protected function handleResponse($expectedTag, DOMElement $dom)
    {
        switch ($dom->tagName) {
            case 'init':
                if ('init' !== $expectedTag) {
                    return false;
                }

                $this->status = $dom->getAttribute('status') ?: $this->status;
                $this->reason = $dom->getAttribute('reason') ?: $this->reason;

                return true;

            case 'response':
                if ('response' !== $expectedTag) {
                    return false;
                }

                $this->status = $dom->getAttribute('status') ?: $this->status;
                $this->reason = $dom->getAttribute('reason') ?: $this->reason;
                $child = $dom->firstChild;

                if (self::REASON_ERROR !== $this->reason
                    && self::REASON_EXCEPTION !== $this->reason
                    && $child instanceof DOMElement
                    && 'error' === $child->tagName
                ) {
                    $command = $dom->getAttribute('command') ?: '-';

                    throw new DbgpDebuggerException(sprintf('DBGP error (%s): %s', $command, $child->textContent), (int) $child->getAttribute('code'));
                }

                return true;

            case 'stream':
                if ('stdout' !== $dom->getAttribute('type')) {
                    return false;
                }

                if ('base64' === $dom->getAttribute('encoding')) {
                    $this->stdoutData .= base64_decode($dom->textContent);
                } else {
                    $this->stdoutData .= $dom->textContent;
                }

                return true;
        }

        return false;
    }

    protected function updateBreakpoints()
    {
        foreach ($this->breakpoints as $id => $bp) {
            $bpUpdateId = $bp->getUpdateId();

            if ($bpUpdateId !== $this->breakpointsUpdateIds[$id]) {
                $args = array(
                    '-d' => $id,
                    '-s' => $bp->getState(),
                    '-n' => $bp->getLineNo(),
                    '-h' => $bp->getHitValue(),
                    '-o' => $bp->getHitCondition(),
                );

                $this->send('breakpoint_update', $args);
                $this->breakpointsUpdateIds[$id] = $bpUpdateId;
            }
        }
    }

    protected function mapProperty(DOMElement $prop)
    {
        $map = array(
            'name' => $prop->getAttribute('name'),
            'fullname' => $prop->getAttribute('fullname'),
            'type' => $prop->getAttribute('type'),
            'facet' => $prop->getAttribute('facet'),
            'classname' => $prop->getAttribute('classname'),
            'constant' => (bool) $prop->getAttribute('constant'),
            'children' => (bool) $prop->getAttribute('children'),
            'size' => (int) $prop->getAttribute('size'),
            'page' => (int) $prop->getAttribute('page'),
            'pagesize' => (int) $prop->getAttribute('pagesize'),
            'address' => (int) $prop->getAttribute('address'),
            'key' => $prop->getAttribute('key'),
            'numchildren' => (int) $prop->getAttribute('numchildren'),
            'value' => array(),
        );

        if ($map['children']) {
            foreach ($prop->childNodes as $prop) {
                $prop = $this->mapProperty($prop);
                $map['value'][$prop['name']] = $prop;
            }
        } elseif ('base64' === $prop->getAttribute('encoding')) {
            $map['value'] = base64_decode($prop->textContent);
        } else {
            $map['value'] = $prop->textContent;
        }

        return $map;
    }
}
