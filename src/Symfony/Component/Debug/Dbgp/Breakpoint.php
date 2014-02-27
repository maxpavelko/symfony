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

/**
 * DBGP breakpoint.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Breakpoint
{
    const STATE_ENABLED = 'enabled';
    const STATE_DISABLED = 'disabled';

    const HIT_GREATER = '>=';
    const HIT_EQUALS = '==';
    const HIT_MODULO = '%';

    const EXCEPTION_XDEBUG_FATAL_ERROR = 'Fatal error';
    const EXCEPTION_XDEBUG_CATCHABLE_FATAL_ERROR = 'Catchable fatal error';
    const EXCEPTION_XDEBUG_WARNING = 'Warning';
    const EXCEPTION_XDEBUG_PARSE_ERROR = 'Parse error';
    const EXCEPTION_XDEBUG_NOTICE = 'Notice';
    const EXCEPTION_XDEBUG_STRICT_STANDARDS = 'Strict standards';
    const EXCEPTION_XDEBUG_DEPRECATED = 'Deprecated';
    const EXCEPTION_XDEBUG_XDEBUG = 'Xdebug';
    const EXCEPTION_XDEBUG_UNKNOWN_ERROR = 'Unknown error';

    private $type;
    private $state = self::STATE_ENABLED;
    private $fileName;
    private $lineNo;
    private $function;
    private $exception;
    private $hitValue;
    private $hitCondition;
    private $temporary;
    private $expression;
    private $updateId = 1;


    /**
     * Break on the given lineno in the given file.
     */
    public static function onLine($fileName, $lineNo, $temporary = false)
    {
        $bp = new static('line', $temporary);
        $bp->fileName = $fileName;
        $bp->lineNo = $lineNo;

        return $bp;
    }

    /**
     * Break when the given expression is true at the given filename and line number.
     */
    public static function onCondition($expression, $fileName, $lineNo = null, $temporary = false)
    {
        $bp = new static('conditional', $temporary);
        $bp->expression = $expression;
        $bp->fileName = $fileName;
        $bp->lineNo = $lineNo;

        return $bp;
    }

    /**
     * Break on entry into new stack for function name.
     */
    public static function onCall($function, $temporary = false)
    {
        $bp = new static('call', $temporary);
        $bp->function = $function;

        return $bp;
    }

    /**
     * Break on exit from stack for function name.
     */
    public static function onReturn($function, $temporary = false)
    {
        $bp = new static('return', $temporary);
        $bp->function = $function;

        return $bp;
    }

    /**
     * Break on exception of the given name.
     */
    public static function onException($exception, $temporary = false)
    {
        $bp = new static('exception', $temporary);
        $bp->exception = $exception;

        return $bp;
    }

    /**
     * Break on write of the variable or address defined by the expression argument.
     *
     * Not available with Xdebug.
     */
    public static function onChange($property, $temporary = false)
    {
        $bp = new static('watch', $temporary);
        $bp->expression = $property;

        return $bp;
    }

    protected function __construct($type, $temporary)
    {
        $this->type = $type;
        $this->temporary = (int) (bool) $temporary;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        if ($lineNo !== $this->lineNo) {
            $this->state = $state;
            ++$this->updateId;
        }
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function getLineNo()
    {
        return $this->lineNo;
    }

    public function setLineNo($lineNo)
    {
        if ($lineNo !== $this->lineNo) {
            $this->lineNo = $lineNo;
            ++$this->updateId;
        }
    }

    public function getFunction()
    {
        return $this->function;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function getHitValue()
    {
        return $this->hitValue;
    }

    public function setHitValue($hitValue)
    {
        if ($hitValue !== $this->hitValue) {
            $this->hitValue = $hitValue;
            ++$this->updateId;
        }
    }

    public function getHitCondition()
    {
        return $this->hitCondition;
    }

    public function setHitCondition($hitCondition)
    {
        if ($hitCondition !== $this->hitCondition) {
            $this->hitCondition = $hitCondition;
            ++$this->updateId;
        }
    }

    public function isTemporary()
    {
        return $this->temporary;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function getUpdateId()
    {
        return $this->updateId;
    }
}
