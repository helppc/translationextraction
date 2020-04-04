<?php declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor;

use HelpPC\TranslationExtraction\Extractor\Filters\IFilter;

interface IExtractor
{
    public function setupForms(): self;

    public function setMeta(string $key, string $value): self;

    public function addComment(string $value): self;

    public function getMeta(string $key): ?string;

    public function setupDataGrid(): self;

    /**
     * @param string[] $resource
     * @return $this
     */
    public function scan(array $resource): self;

    public function getFilter(string $filterName): IFilter;

    public function setFilter(string $extension, string $filterName): self;

    public function addFilter(string $filterName, IFilter $filter): self;

    public function removeAllFilters(): self;

    /**
     * @param string $outputFile
     * @param array<string,mixed>|NULL $data
     * @return $this
     */
    public function save(string $outputFile, array $data = NULL): self;

    /**
     * @param array<string,mixed> $data
     * @return string
     */
    public function formatData(array $data): string;

    public function setLogCallback(callable $callable):void;

}