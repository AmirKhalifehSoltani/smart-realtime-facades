<?php

namespace Imanghafoori\RealtimeFacades;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;

class SmartRealTimeFacadesProvider extends ServiceProvider
{
    private static $facadeNamespace = '_Facades';

    public function register()
    {
        spl_autoload_register(function ($alias) {
            if (Str::startsWith($alias, [self::$facadeNamespace.'\\'])) {
                require self::ensureFacadeExists($alias);
            }
        });
    }

    public static function ensureFacadeExists($alias)
    {
        if (is_file($path = storage_path('framework/cache/facade-'.sha1($alias).'.php'))) {
            return $path;
        }

        file_put_contents($path, self::formatFacadeStub($alias, self::getStub()));

        return $path;
    }

    protected static function formatFacadeStub($alias, $stub)
    {
        $replacements = [
            str_replace('/', '\\', dirname(str_replace('\\', '/', $alias))),
            class_basename($alias),
            substr($alias, strlen(static::$facadeNamespace)),
        ];

        try {
            $replacements[3] = self::getMethodsDocblock($replacements[2]);
        } catch (Throwable $e) {
            $replacements[3] = '';
        }

        return str_replace(
            ['DummyNamespace', 'DummyClass', 'DummyTarget', 'DummyDocs'], $replacements, $stub
        );
    }

    public static function getMethodsDocblock($class): string
    {
        $publicMethods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);

        $methodsDoc = '';
        foreach ($publicMethods as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $params = $method->getParameters();
            $signature = '';
            foreach ($params as $param) {
                $name = $param->getName();
                $type = $param->getType();
                if ($type) {
                    $type = $type.' ';
                }
                $defaultValue = self::getDefaultValue($param);

                $signature = $signature.$type.'$'.$name.$defaultValue.', ';
            }
            $signature = '('.trim($signature, ', ').')';

            $returnType = $method->hasReturnType() ? $method->getReturnType().' ' : '';
            $methodName = $method->getName();
            $methodsDoc .= ' * @method static '.$returnType.$methodName.$signature.PHP_EOL;
        }
        $methodsDoc .= ' *';

        return $methodsDoc;
    }

    public static function getDefaultValue(ReflectionParameter $param): string
    {
        if (! $param->isDefaultValueAvailable()) {
            return '';
        }

        $defaultValue = $param->getDefaultValue();

        if (is_array($defaultValue)) {
            $strDefaultValue = '[';
            foreach ($defaultValue as $element) {
                $element = is_string($element) ? '"'.$element.'"' : $element;
                $strDefaultValue .= $element.', ';
            }
            $strDefaultValue = trim($strDefaultValue, ', ');
            $defaultValue = $strDefaultValue.']';
        } elseif (is_string($defaultValue)) {
            $defaultValue = '"'.$defaultValue.'"';
        }

        return ' = '.$defaultValue;
    }

    public static function getStub()
    {
        return <<<EOF
<?php

namespace DummyNamespace;

use Illuminate\Support\Facades\Facade;

/**
DummyDocs
 * @see DummyTarget
 */
class DummyClass extends Facade
{
    protected static function getFacadeAccessor()
    {
        return DummyTarget::class;
    }
}

EOF;
    }
}
