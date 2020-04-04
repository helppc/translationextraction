<?php declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Command;

use Nette\Neon\Neon;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class MergeCommand extends Command
{
    /**@var string */
    protected static $defaultName = 'translationExtract:merge';
    /** @var array<string,array<string,string>> */
    private array $scanDirs;
    private string $exportDir;
    /** @var array<string,array<string,array<int,string>>> */
    private array $missingTranslations = [];

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
        $this->addOption('live', 'l', InputOption::VALUE_NONE);

    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $requiredDomains = $input->getOption('domain');

        $transContent = [];
        foreach ($this->scanDirs as $domain => $setting) {
            if (!empty($requiredDomains) && !in_array($domain, $requiredDomains)) {
                $output->writeln(sprintf('[S] Skipping domain %s.', $domain));
                continue;
            }
            if (!is_dir($setting['lang'])) {
                $output->writeln(sprintf('[F] Merge domain %s failed. Folder %s is not accessible.', $domain, $setting['lang']));
            }
            /** @var \SplFileInfo $v2 */
            foreach (Finder::find('*.neon')->from($setting['lang']) as $v2) {
                $match = Strings::match($v2->getFilename(), '~^(?P<domain>.*?)\.(?P<locale>[^\.]+)\.(?P<format>[^\.]+)$~');

                if ($match === NULL) {
                    continue;
                }

                $transContent[$match['locale']][$match['domain']] = Neon::decode(FileSystem::read($v2->getPathname()));

            }
        }

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $completeNotTranslated = Neon::decode(FileSystem::read(sprintf('%snew.neon', $this->getExportFolderPath())));
        foreach ($transContent as $locale => $translations) {
            $this->merge($completeNotTranslated, $translations, $locale, $input, $output, $questionHelper, $input->getOption('live'));
            $outputFile = sprintf('%stranslation_%s.neon', $this->getExportFolderPath(), $locale);
            FileSystem::write($outputFile, Neon::encode($completeNotTranslated, Neon::BLOCK));
        }

        $output->writeln('--------------------');
        $output->writeln('Missing Translations');
        foreach ($this->missingTranslations as $locale => $missingTranslation) {
            $output->writeln('--------------------');
            $output->writeln('Locale = ' . $locale);
            foreach ($missingTranslation as $domain => $translations) {
                $output->writeln(Strings::padRight($domain, 15) . ' | ' . count($translations));
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    foreach ($translations as $key) {
                        $output->writeln(' - ' . Strings::after($key, '.'));
                    }
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $newTranslation
     * @param array<string,mixed> $oldTranslation
     * @param string $locale
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     * @param bool $liveTranslation
     * @param string|null $previousKey
     */
    private function merge(array &$newTranslation, array $oldTranslation, string $locale, InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper, bool $liveTranslation = FALSE, ?string $previousKey = NULL): void
    {
        foreach ($newTranslation as $key => &$value) {
            if (is_array($value)) {
                $this->merge($value, $oldTranslation[$key] ?? [], $locale, $input, $output, $questionHelper, $liveTranslation, ($previousKey ? $previousKey . '.' : NULL) . $key);
                continue;
            }
            if (!isset($oldTranslation[$key])) {
                $translation = 'NOT TRANSLATED';
                $this->touchMissingTranslation($locale, $previousKey . '.' . $key);
                if ($liveTranslation) {
                    $question = new Question(sprintf('What is %s in %s? ', $previousKey . '.' . $key, $locale), '');
                    $translation = $questionHelper->ask($input, $output, $question);
                }
                $newTranslation[$key] = $translation;
                continue;
            }

            $newTranslation[$key] = $oldTranslation[$key];
        }
    }

    private function touchMissingTranslation(string $locale, string $key): void
    {
        if (!isset($this->missingTranslations[$locale][Strings::before($key, '.')])) {
            $this->missingTranslations[$locale][Strings::before($key, '.')] = [];
        }
        $this->missingTranslations[$locale][Strings::before($key, '.')][] = $key;
    }

    public function getExportFolderPath(): string
    {
        return Strings::endsWith('/', $this->exportDir) ? $this->exportDir : $this->exportDir . '/';
    }
}
