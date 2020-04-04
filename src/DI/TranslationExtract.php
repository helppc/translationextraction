<?php declare(strict_types=1);

namespace HelpPC\TranslationExtraction\DI;

use Contributte\Translation\Exceptions\InvalidArgument;
use HelpPC\TranslationExtraction\Command\CompleteCommand;
use HelpPC\TranslationExtraction\Command\ExtractCommand;
use HelpPC\TranslationExtraction\Command\MergeCommand;
use HelpPC\TranslationExtraction\Extractor\Extractor;
use HelpPC\TranslationExtraction\Extractor\IExtractor;
use HelpPC\TranslationExtraction\Extractor\NeonExtractor;
use Nette;
use Nette\Schema\Expect;

class TranslationExtract extends Nette\DI\CompilerExtension
{
    /** @var object */
    protected $config;

    public function getConfigSchema(): Nette\Schema\Schema
    {
        $builder = $this->getContainerBuilder();

        return Expect::structure([
            'debug' => Expect::bool($builder->parameters['debugMode']),
            'extractors' => Expect::array()->default([
                'neon' => NeonExtractor::class,
            ]),
            'metas' => Expect::array()->default([])->assert(function (array $metas): bool {
                if (empty($metas)) {
                    return TRUE;
                }
                foreach ($metas as $key => $value) {
                    if (!is_string($key) || (!is_string($value) && !is_int($value))) {
                        throw new InvalidArgument('Meta must contains only int or string and key must be string only.');
                    }
                    return TRUE;
                }
            }),
            'exportDir' => Expect::string(NULL)->assert(function (string $path): bool {
                if (!is_dir($path)) {
                    throw new InvalidArgument(sprintf('Folder %s doesn\'t exists.', $path));
                }
                return TRUE;
            }),
            'scanDirs' => Expect::array()->assert(function (array $dirs): bool {
                if (empty($dirs)) {
                    throw new InvalidArgument('scanDirs can\'t be empty!');
                }
                foreach ($dirs as $translationDomain => $settings) {
                    if (!isset($settings['scan']) || !is_dir($settings['scan'])) {
                        throw new InvalidArgument(sprintf('Bad scan folder for domain %s.', $translationDomain));
                    }
                    if (!isset($settings['lang']) || !is_dir($settings['lang'])) {
                        throw new InvalidArgument(sprintf('Bad lang folder for domain %s.', $translationDomain));
                    }
                }
                return TRUE;
            }),
        ]);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        $extractCommand = $builder->addDefinition($this->prefix('console.translationExtractCommand'))
            ->setFactory(ExtractCommand::class)
            ->setArgument(0, $this->config->exportDir)
            ->setArgument(1, $this->config->scanDirs)
            ->setArgument(2, $this->config->metas);
        $builder->addDefinition($this->prefix('console.translationMergerCommand'))
            ->setFactory(MergeCommand::class)
            ->setArgument(0, $this->config->exportDir)
            ->setArgument(1, $this->config->scanDirs);
        $builder->addDefinition($this->prefix('console.completeCommand'))
            ->setFactory(CompleteCommand::class)
            ->setArgument(0, $this->config->exportDir)
            ->setArgument(1, $this->config->scanDirs);

        foreach ($this->config->extractors as $k1 => $v1) {
            $reflection = new \ReflectionClass($v1);

            if (!$reflection->implementsInterface(IExtractor::class)) {
                throw new InvalidArgument('Extractor must implement interface "' . Extractor::class . '".');
            }

            $loader = $builder->addDefinition($this->prefix('extractor.' . Nette\Utils\Strings::firstUpper($k1)))
                ->setFactory($v1)
                ->setArgument(0, NULL);

            $extractCommand->addSetup('addExtractor', [$k1, $loader]);
        }
    }

}