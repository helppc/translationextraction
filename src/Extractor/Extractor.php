<?php
declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor;

use HelpPC\TranslationExtraction\Extractor\Filters\IFilter;
use HelpPC\TranslationExtraction\Extractor\Filters\LatteFilter;
use HelpPC\TranslationExtraction\Extractor\Filters\PHPFilter;
use Nette\Utils\FileSystem;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class Extractor implements IExtractor
{
    public const CONTEXT = 'context';
    public const SINGULAR = 'singular';
    public const PLURAL = 'plural';
    public const LINE = 'line';
    public const FILE = 'file';
    /** @var NULL|callable */
    private $logCallback = NULL;
    /** @var string[] */
    protected array $inputFiles = [];

    /** @var array<string,string[]> */
    protected array $filters = array(
        'php' => array('PHP'),
    );

    /**
     * @var array<string,string>
     */
    protected array $meta = [];

    /**
     * @var string[]
     */
    protected array $comments = [];
    private ?LoggerInterface $logger = NULL;
    /**
     * @var array<string,IFilter>
     */
    protected array $filterStore = [];
    /**
     * @var array<string,mixed>
     */
    protected array $data = [];

    public function __construct(?LoggerInterface $logger = NULL)
    {

        $this->logger = $logger;
        // Clean up...
        $this->removeAllFilters();
        // Set basic filters
        $this->setFilter('php', 'PHP')
            ->setFilter('phtml', 'PHP')
            ->setFilter('phtml', 'Latte')
            ->setFilter('latte', 'PHP')
            ->setFilter('latte', 'Latte');

        $this->addFilter('Latte', new Filters\LatteFilter());

        $phpFilter = $this->getFilter('PHP');
        assert($phpFilter instanceof PHPFilter);

        $phpFilter->addFunction('translate');

        $latteFilter = $this->getFilter('Latte');
        assert($latteFilter instanceof LatteFilter);

        $latteFilter->addFunction('!_')
            ->addFunction('_');
    }

    public function setupForms(): self
    {
        $php = $this->getFilter('PHP');
        assert($php instanceof PHPFilter);

        $php->addFunction('setText')
            ->addFunction('addButton', 2)
            ->addFunction('addCheckbox', 2)
            ->addFunction('addError')
            ->addFunction('addFile', 2) // Nette 0.9
            ->addFunction('addGroup')
            ->addFunction('addImage', 3)
            ->addFunction('addMultiSelect', 2)
            ->addFunction('addMultiSelect', 3)
            ->addFunction('addPassword', 2)
            ->addFunction('addRadioList', 2)
            ->addFunction('addRadioList', 3)
            ->addFunction('addRule', 2)
            ->addFunction('addSelect', 2)
            ->addFunction('addSelect', 3)
            ->addFunction('addSubmit', 2)
            ->addFunction('addText', 2)
            ->addFunction('addTextArea', 2)
            ->addFunction('addUpload', 2) // Nette 2.0
            ->addFunction('setRequired')
            ->addFunction('skipFirst') // Nette 0.9
            ->addFunction('setPrompt') // Nette 2.0
            ->addFunction('addProtection');

        return $this;
    }

    public function setMeta(string $key, string $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function addComment(string $value): self
    {
        $this->comments[] = $value;
        return $this;
    }

    public function getMeta(string $key): ?string
    {
        return $this->meta[$key] ?? NULL;
    }

    public function setupDataGrid(): self
    {
        $php = $this->getFilter('PHP');
        assert($php instanceof PHPFilter);

        $php->addFunction('addColumn', 2)
            ->addFunction('addNumericColumn', 2)
            ->addFunction('addDateColumn', 2)
            ->addFunction('addCheckboxColumn', 2)
            ->addFunction('addImageColumn', 2)
            ->addFunction('addPositionColumn', 2)
            ->addFunction('addActionColumn')
            ->addFunction('addAction', 2)
            ->addFunction('addColumnNumber', 2)
            ->addFunction('addColumnText', 2)
            ->addFunction('addColumnDateTime', 2)
            ->addFunction('setPrompt', 1)
            ->addFunction('setText', 1)
            ->addFunction('setTitle', 1)
            ->addFunction('addExportCsv', 1)
            ->addFunction('addFilterMultiSelect', 2)
            ->addFunction('addOption', 2);

        return $this;
    }

    protected function log(string $message): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->debug($message);
        }
        if (is_callable($this->logCallback)) {
            call_user_func($this->logCallback, $message);
        }
    }

    /**
     * @param string $message
     * @throws RuntimeException
     */
    protected function throwException(string $message): void
    {
        $message = $message ?: 'Something unexpected occured. See Translation extractor log for details';
        $this->log($message);
        throw new RuntimeException($message);
    }

    /**
     * @param string[] $resource
     * @return self
     */
    public function scan(array $resource): self
    {
        $this->inputFiles = [];
        foreach ($resource as $item) {
            $this->log("Scanning '$item'");
            $this->_scan($item);
        }
        $this->_extract($this->inputFiles);
        return $this;
    }

    private function _scan(string $resource): void
    {
        if (is_file($resource)) {
            $this->inputFiles[] = $resource;
        } elseif (is_dir($resource)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($resource, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $fileExtension = \mb_strtolower(pathinfo($file->getPathName(), PATHINFO_EXTENSION));
                if (isset($this->filters[$fileExtension])) {
                    $this->inputFiles[] = $file->getPathName();
                }
            }
        } else {
            $this->throwException("Resource '$resource' is not a directory or file");
        }
    }

    public function setLogCallback(callable $callable): void
    {
        $this->logCallback = $callable;
    }

    /**
     * @param string[] $inputFiles
     * @return array<string,mixed>
     */
    private function _extract(array $inputFiles): array
    {
        $inputFiles = array_unique($inputFiles);
        sort($inputFiles);
        foreach ($inputFiles as $inputFile) {
            if (!file_exists($inputFile)) {
                $this->throwException('ERROR: Invalid input file specified: ' . $inputFile);
            }
            if (!is_readable($inputFile)) {
                $this->throwException('ERROR: Input file is not readable: ' . $inputFile);
            }

            $this->log('Extracting data from file ' . $inputFile);

            $fileExtension = \mb_strtolower(\pathinfo($inputFile, PATHINFO_EXTENSION));
            if (isset($this->filters[$fileExtension])) {
                $this->log('Processing file ' . $inputFile);

                foreach ($this->filters[$fileExtension] as $filterName) {
                    $filter = $this->getFilter($filterName);
                    $filterData = $filter->extract($inputFile);
                    $this->log('  Filter ' . $filterName . ' applied');
                    $this->addMessages($filterData, $inputFile);
                }
            }
        }
        return $this->data;
    }

    public function getFilter(string $filterName): IFilter
    {
        if (!isset($this->filterStore[$filterName])) {
            $this->throwException("ERROR: Filter '$filterName' not found.");
        }
        return $this->filterStore[$filterName];
    }

    public function setFilter(string $extension, string $filterName): self
    {
        $extension = \mb_strtolower($extension);
        if (!isset($this->filters[$extension]) || !in_array($filterName, $this->filters[$extension], TRUE)) {
            $this->filters[$extension][] = $filterName;
        }
        return $this;
    }

    public function addFilter(string $filterName, IFilter $filter): self
    {
        $this->filterStore[$filterName] = $filter;
        return $this;
    }

    /**
     * Removes all filter settings in case we want to define a brand new one
     *
     * @return self
     */
    public function removeAllFilters(): self
    {
        $this->filters = [];
        return $this;
    }

    /**
     * @param string $outputFile
     * @param array<string,mixed> $data
     * @return self
     */
    public function save(string $outputFile, array $data = NULL): self
    {
        FileSystem::write($outputFile, $this->formatData($data ?: $this->data));
        return $this;
    }

    /**
     * @param mixed[] $messages
     * @param string $file
     */
    private function addMessages(array $messages, string $file): void
    {
        foreach ($messages as $message) {
            $key = '';
            if (isset($message[self::CONTEXT])) {
                $key .= $message[self::CONTEXT];
            }
            $key .= chr(4);
            $key .= $message[self::SINGULAR];
            $key .= chr(4);
            if (isset($message[self::PLURAL])) {
                $key .= $message[self::PLURAL];
            }
            if ($key === chr(4) . chr(4)) {
                continue;
            }
            $line = $message[self::LINE];
            if (!isset($this->data[$key])) {
                unset($message[self::LINE]);
                $this->data[$key] = $message;
                $this->data[$key]['files'] = [];
            }
            $this->data[$key]['files'][] = array(
                self::FILE => $file,
                self::LINE => $line,
            );
        }
    }
}
