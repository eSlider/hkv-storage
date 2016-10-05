<?php
namespace Eslider\Entity;

/**
 * Class BaseEntity
 *
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class BaseEntity
{
    /**
     * BaseEntity constructor.
     *
     * @param array $data
     */
    public function __construct(array &$data = null)
    {
        if ($data) {
            if (isset($data['@attributes'])) {
                $this->fill($data['@attributes']);
            }
            $this->fill($data);
        }
    }

    /**
     * @param array $data
     * @internal param $methods
     * @internal param $vars
     */
    protected function fill(array &$data)
    {
        static $className, $methods, $vars, $reflection;

        if (!$className) {
            $className  = get_class($this);
            $methods    = get_class_methods($className);
            $vars       = array_keys(get_class_vars($className));
            $reflection = new \ReflectionClass($className);
        }

        foreach ($data as $k => $v) {
            if ($k == "@attributes") {
                continue;
            }

            $methodName = 'set' . ucfirst($k);
            if (in_array($methodName, $methods)) {
                $this->{$methodName}($v);
                continue;
            }

            $methodName = 'set' . ucfirst($this->removeNameSpaceFromVariableName($k));
            if (in_array($methodName, $methods)) {
                $this->{$methodName}($v);
                continue;
            }

            $varName = lcfirst($this->removeNameSpaceFromVariableName($k));
            if (in_array($varName, $vars)) {
                $docComment = $reflection->getProperty($varName)->getDocComment();
                if (preg_match('/@var ([\\\]?[A-Z]\S+)/s', $docComment, $annotations)) {
                    $varClassName = $annotations[1];
                    if (class_exists($varClassName)) {
                        $v = new $varClassName($v);
                    }
                }
                $this->{$varName} = $v;
                continue;
            }

            $varName .= "s";
            if (in_array($varName, $vars)) {
                $docComment = $reflection->getProperty($varName)->getDocComment();
                if ($annotations = self::parse('/@var\s+([\\\]?[A-Z]\S+)(\[\])/s', $docComment)) {
                    $varClassName = $annotations[1];
                    if (class_exists($varClassName)) {
                        $items     = array();
                        $isNumeric = is_int(key($v));
                        $list      = $isNumeric ? $v : array($v);
                        foreach ($list as $subData) {
                            $items[] = new $varClassName($subData);
                        }
                        $v = $items;
                    }
                }
                $this->{$varName} = $v;
                continue;
            }
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    private function removeNameSpaceFromVariableName($name)
    {
        return preg_replace("/^.+?_/", '', $name);
    }


    /**
     * @param $reg
     * @param $str
     * @return null
     */
    public static function parse($reg, $str)
    {
        $annotations = null;
        preg_match($reg, $str, $annotations);
        return $annotations;
    }

    /**
     * Convert to string
     */
    public function __toString()
    {
        $className  = get_class($this);
        $methods    = get_class_methods($className);
        $vars       = array_keys(get_class_vars($className));
        $reflection = new \ReflectionClass($className);
        $data       = array();

        foreach ($vars as $key) {
            $value = $this->$key;
            $data[ $key ] = $value;
        }

        foreach ($methods as $methodName) {
            if (strpos('get', $methodName) !== 0) {
                continue;
            }
            $key          = lcfirst(substr($methodName, 3));
            $vars[ $key ] = $this->{$methodName}();
        }

        $data = $this->objectToString($data);

        return json_encode($data);
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function objectToString($data)
    {
        foreach ($data as $k => $value) {
            if ($value instanceof BaseEntity) {
                $data[ $k ] = (string)$value;
            } elseif (is_array($value)) {
                $value      = $this->objectToString($value);
                $data[ $k ] = $value;

            }
        }
        return $data;
    }
}