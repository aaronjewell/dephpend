<?php

declare(strict_types=1);

namespace Mihaeu\PhpDependencies\Analyser;

use InvalidArgumentException;
use Mihaeu\PhpDependencies\Dependencies\Clazz;
use Mihaeu\PhpDependencies\Dependencies\DependencyFactory;
use Mihaeu\PhpDependencies\Dependencies\DependencyMap;
use Mihaeu\PhpDependencies\Dependencies\DependencySet;
use SplFileInfo;

/**
 * Analyses XDebug function traces as described in https://xdebug.org/docs/execution_trace
 */
class XDebugFunctionTraceAnalyser
{
    public const PARAMETER_START_INDEX = 11;
    public const FUNCTION_NAME_INDEX = 5;

    public function __construct(private DependencyFactory $dependencyFactory)
    {
    }

    public function analyse(SplFileInfo $file): DependencyMap
    {
        $fileHandle = @fopen($file->getPathname(), 'r');
        if (!$fileHandle) {
            throw new InvalidArgumentException('Unable to open trace file for reading');
        }

        $line = fgets($fileHandle);
        $dependencies = new DependencyMap();
        while ($line !== false) {
            $dependencies = $this->extractDependenciesFromLine($dependencies, $line);
            $line = fgets($fileHandle);
        }
        fclose($fileHandle);
        return $dependencies;
    }

    private function extractDependenciesFromLine(DependencyMap $dependencies, string $line): DependencyMap
    {
        $tokens = $this->extractFields($line);
        if ($this->isNotMethodEntryTrace($tokens)
            || $this->containsOnlyScalarValues($tokens)
            || $this->isGlobalFunction($tokens)) {
            return $dependencies;
        }
        return $dependencies->addSet(
            $this->extractFromClass($tokens),
            $this->extractToSet($tokens)
        );
    }

    /**
     * @param list<string> $tokens
     */
    private function isNotMethodEntryTrace(array $tokens): bool
    {
        return count($tokens) <= self::PARAMETER_START_INDEX;
    }

    /**
     * @return list<string>
     */
    private function extractFields(string $line): array
    {
        return explode("\t", str_replace("\n", '', $line));
    }

    /**
     * @param list<string> $tokens
     */
    private function containsOnlyScalarValues(array $tokens): bool
    {
        return strpos(implode('', array_slice($tokens, self::PARAMETER_START_INDEX)), 'class ') === false;
    }

    /**
     * @param list<string> $tokens
     */
    private function isGlobalFunction(array $tokens): bool
    {
        return strpos($tokens[self::FUNCTION_NAME_INDEX], '->') === false
            && strpos($tokens[self::FUNCTION_NAME_INDEX], '::') === false;
    }

    /**
     * @param list<string> $tokens
     */
    private function extractFromClass(array $tokens): Clazz
    {
        $classWithoutMethod = preg_split('/(->)|(::)/', $tokens[self::FUNCTION_NAME_INDEX]);
        $classParts = explode("\\", $classWithoutMethod[0]);
        return $this->dependencyFactory->createClazzFromStringArray($classParts);
    }

    /**
     * @param list<string> $tokens
     */
    private function extractToSet(array $tokens): DependencySet
    {
        return array_reduce(array_slice($tokens, self::PARAMETER_START_INDEX), function (DependencySet $set, string $token) {
            if (strpos($token, 'class') === false) {
                return $set;
            }

            $classParts = explode('\\', str_replace('class ', '', $token));
            return $set->add($this->dependencyFactory->createClazzFromStringArray($classParts));
        }, new DependencySet());
    }
}
