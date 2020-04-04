<?php
declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor\Filters;

use HelpPC\TranslationExtraction\Exceptions\InvalidArgument;
use HelpPC\TranslationExtraction\Extractor\Extractor;

abstract class AFilter
{

    /** @var array<string,array<int,array<string,mixed>>> */
    protected array $functions = [];

    public function addFunction(string $functionName, int $singular = 1, int $plural = NULL, int $context = NULL): self
    {
        if ($singular <= 0) {
            throw new InvalidArgument('Invalid argument type or value given for parameter $singular.');
        }
        $function = array(
            Extractor::SINGULAR => $singular,
        );
        if ($plural !== NULL) {
            if ($plural <= 0) {
                throw new InvalidArgument('Invalid argument type or value given for parameter $plural.');
            }
            $function[Extractor::PLURAL] = $plural;
        }
        if ($context !== NULL) {
            if ($context <= 0) {
                throw new InvalidArgument('Invalid argument type or value given for parameter $context.');
            }
            $function[Extractor::CONTEXT] = $context;
        }
        $this->functions[$functionName][] = $function;
        return $this;
    }

    public function removeFunction(string $functionName): self
    {
        unset($this->functions[$functionName]);
        return $this;
    }

    public function removeAllFunctions(): self
    {
        $this->functions = array();
        return $this;
    }
}
