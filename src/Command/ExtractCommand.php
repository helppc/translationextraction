<?php declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Command;

use HelpPC\TranslationExtraction\Extractor\IExtractor;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractCommand extends Command
{
    /**@var string */
    protected static $defaultName = 'translationExtract:extract';
    /** @var array[] */
    private array $scanDirs;
    private string $exportDir;
    /** @var string[]|array<string,string> */
    private array $metas;
    /** @var IExtractor[]|array<string,IExtractor> */
    private array $extractors = [];

    /**
     * ExtractCommand constructor.
     * @param string $exportDir
     * @param array<string,array<string,string>> $scanDirs
     * @param array<string,string> $metas
     */
    public function __construct(string $exportDir, array $scanDirs, array $metas)
    {
        parent::__construct(NULL);
        $this->scanDirs = $scanDirs;
        $this->exportDir = $exportDir;
        $this->metas = $metas;
    }

    public function addExtractor(string $type, IExtractor $extractor): void
    {
        $this->extractors[$type] = $extractor;
    }

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
        $this->setDescription('Run a Help PC Translation extractor');
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '', NULL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $requiredTypes = $input->getOption('type');
        /**
         * @var string $type
         * @var IExtractor $extractor
         */
        foreach ($this->extractors as $type => $extractor) {
            if (!empty($requiredTypes) && !in_array($type, $requiredTypes)) {
                $output->writeln(sprintf('[S] Skipping %s type.', $type));
                continue;
            }
            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $extractor->setLogCallback(function (string $message) use ($output): void {
                    $output->writeln($message);
                });
            }
            $outputFile = sprintf('%snew.%s', $this->getExportFolderPath(), $type);
            $output->writeln(sprintf('[.] Processing %s type to %s.', $type, $outputFile));
            $extractor->setupForms()->setupDataGrid();
            foreach ($this->metas as $key => $meta) {
                $extractor->setMeta($key, $meta);
            }
            $extractor->scan($this->getScanFolders());
            $extractor->save($outputFile);
            $output->writeln(sprintf('[X] Completed export %s type to %s.', $type, $outputFile));
        }

        return 0;
    }

    public function getExportFolderPath(): string
    {
        return Strings::endsWith('/', $this->exportDir) ? $this->exportDir : $this->exportDir . '/';
    }

    /**
     * @return string[]
     */
    private function getScanFolders(): array
    {
        return array_column($this->scanDirs, 'scan');
    }

}
