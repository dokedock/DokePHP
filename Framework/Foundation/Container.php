<?php

/**
 * 文件作用：轻量容器（DI），支持 bind/singleton 与基于构造函数的简单自动注入。
 */

namespace Framework\Foundation;

class Container
{
    protected $bindings = array();
    protected $instances = array();

    public function bind($id, $concrete, $shared = false)
    {
        $this->bindings[(string) $id] = array(
            'concrete' => $concrete,
            'shared' => (bool) $shared,
        );
        return $this;
    }

    public function singleton($id, $concrete)
    {
        return $this->bind($id, $concrete, true);
    }

    public function instance($id, $object)
    {
        $this->instances[(string) $id] = $object;
        return $this;
    }

    public function has($id)
    {
        $id = (string) $id;
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->bindings) || class_exists($id);
    }

    public function make($id)
    {
        $id = (string) $id;
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->bindings)) {
            $b = $this->bindings[$id];
            $obj = $this->build($b['concrete']);
            if (!empty($b['shared'])) {
                $this->instances[$id] = $obj;
            }
            return $obj;
        }

        if (class_exists($id)) {
            return $this->build($id);
        }

        throw new \InvalidArgumentException('container_not_found');
    }

    protected function build($concrete)
    {
        if (is_callable($concrete)) {
            return call_user_func($concrete, $this);
        }
        if (is_object($concrete)) {
            return $concrete;
        }
        if (is_string($concrete) && class_exists($concrete)) {
            $ref = new \ReflectionClass($concrete);
            if (!$ref->isInstantiable()) {
                throw new \InvalidArgumentException('container_not_instantiable');
            }

            $ctor = $ref->getConstructor();
            if ($ctor === null) {
                return $ref->newInstance();
            }

            $args = array();
            $params = $ctor->getParameters();
            foreach ($params as $p) {
                $clsName = null;
                if (method_exists($p, 'getType')) {
                    $type = $p->getType();
                    if ($type) {
                        if (is_object($type) && get_class($type) === 'ReflectionNamedType') {
                            if (!$type->isBuiltin()) {
                                $clsName = $type->getName();
                            }
                        } elseif (is_object($type) && get_class($type) === 'ReflectionUnionType' && method_exists($type, 'getTypes')) {
                            $ts = $type->getTypes();
                            $pick = null;
                            if (is_array($ts)) {
                                foreach ($ts as $one) {
                                    if (is_object($one) && get_class($one) === 'ReflectionNamedType' && !$one->isBuiltin()) {
                                        if ($pick !== null) {
                                            $pick = null;
                                            break;
                                        }
                                        $pick = $one->getName();
                                    }
                                }
                            }
                            if ($pick !== null) {
                                $clsName = $pick;
                            }
                        }
                    }
                } elseif (method_exists($p, 'getClass')) {
                    $cls = $p->getClass();
                    if ($cls) {
                        $clsName = $cls->getName();
                    }
                }

                if ($clsName !== null && $clsName !== '') {
                    $args[] = $this->make($clsName);
                    continue;
                }
                if ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                    continue;
                }
                $args[] = null;
            }

            return $ref->newInstanceArgs($args);
        }
        return $concrete;
    }
}
