<?php declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Command;

use Nette\Neon\Neon;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CompleteCommand extends Command
{
    /**@var string */
    protected static $defaultName = 'translationExtract:complete';
    /** @var array<string,array<string,string>> */
    private array $scanDirs;
    private string $exportDir;

    /**
     * MergeCommand constructor.
     * @param string $exportDir
     * @param array<string,array<string,string>> $scanDirs
     */
    public function __construct(string $exportDir, array $scanDirs)
    {
        parent::__construct(NULL);
        $this->scanDirs = $scanDirs;
        $this->exportDir = $exportDir;
    }

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
        $this->setDescription('Run a Help PC Translation extractor');
        $this->addOption('domain', 'd', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '', NULL);

    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $requiredDomains = $input->getOption('domain');

        $transContent = [];

        /** @var \SplFileInfo $v2 */
        foreach (Finder::find('translation_*.neon')->from($this->getExportFolderPath()) as $v2) {
            $match = Strings::match($v2->getFilename(), '~^translation\_(?P<locale>[^\.]+)\.neon$~');
            $transContent[$match['locale']] = Neon::decode(
                FileSystem::read($v2->getRealPath())
            );
        }

        foreach ($this->scanDirs as $domain => $setting) {
            if (!empty($requiredDomains) && !in_array($domain, $requiredDomains)) {
                $output->writeln(sprintf('[S] Skipping domain %s.', $domain));
                continue;
            }
            if (!is_dir($setting['lang'])) {
                $output->writeln(sprintf('[F] Merge domain %s failed. Folder %s is not accessible.', $domain, $setting['lang']));
            }
            foreach ($transContent as $locale => $translations) {
                $output->writeln(sprintf('Saving domain %s for language %s', $domain, $locale));
                FileSystem::write(
                    sprintf(
                        '%s%s.%s.neon',
                        $this->getLangFolderPath($setting['lang']),
                        $domain,
                        $locale
                    ),
                    Neon::encode($translations[$domain], Neon::BLOCK)
                );
            }
        }

        return 0;
    }

    public function getExportFolderPath(): string
    {
        return Strings::endsWith('/', $this->exportDir) ? $this->exportDir : $this->exportDir . '/';
    }

    public function getLangFolderPath(string $langFolderPath): string
    {
        return Strings::endsWith('/', $langFolderPath) ? $langFolderPath : $langFolderPath . '/';
    }
}
