<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2015, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace Hoa\Math\Visitor;

use Hoa\Math;
use Hoa\Visitor;

/**
 * Class \Hoa\Math\Visitor\Arithmetic.
 *
 * Evaluate arithmetical expressions.
 *
 * @author     Stéphane Py <py.stephane1@gmail.com>
 * @author     Sébastien Houze <s@verylastroom.com>
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @author     Cédric Dugat <cedric@dugat.me>
 * @copyright  Copyright © 2007-2015 Stéphane Py, Sébastien Houze,
 *             Ivan Enderlin, Cédric Dugat.
 * @license    New BSD License
 */

class Arithmetic implements Visitor\Visit {

    /**
     * List of supported functions: identifier => values as callable
     *
     * @var \ArrayObject object
     */
    protected $_functions = null;

    /**
     * List of constants supported
     *
     * @var \ArrayObject object
     */
    protected $_constants = null;



    /**
     * Initialize constants and functions.
     *
     * @access  public
     * @return  void
     */
    public function __construct ( ) {

        $this->initializeConstants();
        $this->initializeFunctions();

        return;
    }

    /**
     * Visit an element.
     *
     * @access  public
     * @param   \Hoa\Visitor\Element  $element    Element to visit.
     * @param   mixed                 &$handle    Handle (reference).
     * @param   mixed                 $eldnah     Handle (not reference).
     * @return  float
     */
    public function visit ( Visitor\Element $element,
                            &$handle = null, $eldnah = null ) {

        $type     = $element->getId();
        $children = $element->getChildren();

        if(null === $handle)
            $handle = function ( $x ) {

                return $x;
            };

        $acc = &$handle;

        switch($type) {

            case '#function':
                $name      = array_shift($children)->accept($this, $_, $eldnah);
                $function  = $this->getFunction($name);
                $arguments = [];

                foreach($children as $child) {

                    $child->accept($this, $_, $eldnah);
                    $arguments[] = $_();
                    unset($_);
                }

                $acc = function ( ) use ( $function, $arguments, $acc ) {

                    return $acc($function->distributeArguments($arguments));
                };
              break;

            case '#negative':
                $children[0]->accept($this, $a, $eldnah);

                $acc = function ( ) use ( $a, $acc ) {

                    return $acc(-$a());
                };
              break;

            case '#addition':
                $children[0]->accept($this, $a, $eldnah);

                $acc = function ( $b ) use ( $a, $acc ) {

                    return $acc($a() + $b);
                };

                $children[1]->accept($this, $acc, $eldnah);
              break;

            case '#substraction':
                $children[0]->accept($this, $a, $eldnah);

                $acc = function ( $b ) use ( $a, $acc ) {

                    return $acc($a()) - $b;
                };

                $children[1]->accept($this, $acc, $eldnah);
              break;

            case '#multiplication':
                $children[0]->accept($this, $a, $eldnah);

                $acc = function ( $b ) use ( $a, $acc ) {

                    return $acc($a() * $b);
                };

                $children[1]->accept($this, $acc, $eldnah);
              break;

            case '#division':
                $children[0]->accept($this, $a, $eldnah);

                $parent = $element->getParent();

                if(    null === $parent
                   || $type === $parent->getId())
                    $acc = function ( $b ) use ( $a, $acc ) {

                        if(0 === $b)
                            throw new \RuntimeException(
                                'Division by zero is not possible.');

                        return $acc($a()) / $b;
                    };
                else {

                    if('#fakegroup' !== $parent->getId()) {

                        $classname = get_class($element);
                        $group     = new $classname(
                            '#fakegroup',
                            null,
                            [$element],
                            $parent
                        );
                        $element->setParent($group);

                        $this->visit($group, $acc, $eldnah);

                        break;
                    }
                    else
                        $acc = function ( $b ) use ( $a, $acc ) {

                            if(0 === $b)
                                throw new \RuntimeException(
                                    'Division by zero is not possible.');

                            return $acc($a() / $b);
                        };
                }

                $children[1]->accept($this, $acc, $eldnah);
              break;

            case '#fakegroup':
            case '#group':
                $children[0]->accept($this, $a, $eldnah);

                $acc = function ( ) use ( $a, $acc ) {

                    return $acc($a());
                };
              break;

            case 'token':
                $value = $element->getValueValue();
                $out   = null;

                if('constant' === $element->getValueToken()) {

                    if(defined($value))
                        $out = constant($value);
                    else
                        $out = $this->getConstant($value);
                }
                elseif('id' === $element->getValueToken())
                    return $value;
                else
                    $out = (float) $value;

                $acc = function ( ) use ( $out, $acc ) {

                    return $acc($out);
                };
        }

        if(null === $element->getParent())
            return $acc();
    }

    /**
     * Get functions.
     *
     * @access  public
     * @return  \ArrayObject
     */
    public function getFunctions ( ) {

        return $this->_functions;
    }

    /**
     * Get a function.
     *
     * @access  public
     * @param   string  $name    Function name.
     * @return  \Hoa\Core\Consistency\Xcallable
     * @throw   \Hoa\Math\Exception\UnknownFunction
     */
    public function getFunction ( $name ) {

        if(false === $this->_functions->offsetExists($name))
            throw new Math\Exception\UnknownFunction(
                'Function %s does not exist.', 0, $name);

        return $this->_functions[$name];
    }

    /**
     * Get constants.
     *
     * @access  public
     * @return  \ArrayObject
     */
    public function getConstants ( ) {

        return $this->_constants;
    }

    /**
     * Get a constant.
     *
     * @access  public
     * @param   string  $name    Constant name.
     * @return  mixed
     * @throw   \Hoa\Math\Exception\UnknownFunction
     */
    public function getConstant ( $name ) {

        if(false === $this->_constants->offsetExists($name))
            throw new Math\Exception\UnknownConstant(
                'Constant %s does not exist', 1, $name);

        return $this->_constants[$name];
    }

    /**
     * Initialize functions mapping.
     *
     * @access protected
     * @return void
     */
    protected function initializeFunctions ( ) {

        static $_functions = null;

        if(null === $_functions) {

            $average = function ( ) {

                $arguments = func_get_args();

                return array_sum($arguments) / count($arguments);
            };

            $_functions = new \ArrayObject([
                'abs'     => xcallable('abs'),
                'acos'    => xcallable('acos'),
                'asin'    => xcallable('asin'),
                'atan'    => xcallable('atan'),
                'average' => xcallable($average),
                'avg'     => xcallable($average),
                'ceil'    => xcallable('ceil'),
                'cos'     => xcallable('cos'),
                'count'   => xcallable(function ( ) {
                                 return count(func_get_args());
                             }),
                'deg2rad' => xcallable('deg2rad'),
                'exp'     => xcallable('exp'),
                'floor'   => xcallable('floor'),
                'ln'      => xcallable('log'),
                'log'     => xcallable(function ( $value, $base = 10 ) {
                                 return log($value, $base);
                             }),
                'max'     => xcallable('max'),
                'min'     => xcallable('min'),
                'pow'     => xcallable('pow'),
                'rad2deg' => xcallable('rad2deg'),
                'sin'     => xcallable('sin'),
                'sqrt'    => xcallable('sqrt'),
                'sum'     => xcallable(function ( ) {
                                 return array_sum(func_get_args());
                             }),
                'tan'     => xcallable('tan')
            ]);
        }

        $this->_functions = $_functions;

        return;
    }

    /**
     * Initialize constants mapping.
     *
     * @access protected
     * @return void
     */
    protected function initializeConstants ( ) {

        static $_constants = null;

        if(null === $_constants)
            $_constants = new \ArrayObject([
                'PI'      => M_PI,
                'PI_2'    => M_PI_2,
                'PI_4'    => M_PI_4,
                'E'       => M_E,
                'SQRT_PI' => M_SQRTPI,
                'SQRT_2'  => M_SQRT2,
                'SQRT_3'  => M_SQRT3,
                'LN_PI'   => M_LNPI
            ]);

        $this->_constants = $_constants;

        return;
    }

    /**
     * Add a function.
     *
     * @access  public
     * @param   string  $name        Function name.
     * @param   mixed   $callable    Callable.
     * @return  void
     */
    public function addFunction ( $name, $callable = null ) {

        if(null === $callable) {

            if(false === function_exists($name))
                throw new Math\UnknownFunction(
                    'Function %s does not exist, cannot add it.', 2, $name);

            $callable = $name;
        }

        $this->_functions[$name] = xcallable($callable);

        return;
    }

    /**
     * Add a constant.
     *
     * @access  public
     * @param   string  $name     Constant name.
     * @param   mixed   $value    Value.
     * @return  void
     */
    public function addConstant ( $name, $value ) {

        $this->_constants[$name] = $value;

        return;
    }
}
