<?php
declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor\Filters;

interface IFilter
{

    /**
     * @param string $file
     * @return array<int, array<string,string>>
     */
    public function extract(string $file): array;
}
