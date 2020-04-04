<?php
declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor;

use HelpPC\TranslationExtraction\Extractor\Filters\PHPFilter;
use Psr\Log\LoggerInterface;

class GettextExtractor extends Extractor
{
    private const ESCAPE_CHARS = '"\\';

    public function __construct(?LoggerInterface $logFile = NULL)
    {
        $this->addFilter('PHP', new PHPFilter());
        $this->setMeta('POT-Creation-Date', date('c'));
        $this->setMeta('PO-Revision-Date', 'YEAR-MO-DA HO:MI+ZONE');
        $this->setMeta('Last-Translator', 'FULL NAME <EMAIL@ADDRESS>');
        $this->setMeta('MIME-Version', '1.0');
        $this->setMeta('Content-Type', 'text/plain; charset=UTF-8');
        $this->setMeta('Content-Transfer-Encoding', '8bit');
        $this->setMeta('Plural-Forms', 'nplurals=INTEGER; plural=EXPRESSION;');
        $this->addComment('Gettext keys exported by GettextExtractor');
        parent::__construct($logFile);
    }

    /**
     * @param array<string,mixed> $data
     * @return string
     */
    public function formatData(array $data): string
    {
        $output = [];
        foreach ($this->comments as $comment) {
            $output[] = '# ' . $comment;
        }
        $output[] = '#, fuzzy';
        $output[] = 'msgid ""';
        $output[] = 'msgstr ""';
        foreach ($this->meta as $key => $value) {
            $output[] = '"' . $key . ': ' . $value . '\n"';
        }
        $output[] = '';

        foreach ($data as $message) {
            foreach ($message['files'] as $file) {
                $output[] = '#: ' . $file[self::FILE] . ':' . $file[self::LINE];
            }
            if (isset($message[self::CONTEXT])) {
                $output[] = $this->formatMessage($message[self::CONTEXT], 'msgctxt');
            }
            $output[] = $this->formatMessage($message[self::SINGULAR], 'msgid');
            if (isset($message[self::PLURAL])) {
                $output[] = $this->formatMessage($message[self::PLURAL], 'msgid_plural');
                $output[] = 'msgstr[0] ""';
                $output[] = 'msgstr[1] ""';
            } else {
                $output[] = 'msgstr ""';
            }

            $output[] = '';
        }

        return implode("\n", $output);
    }


    protected function formatMessage(string $message, string $prefix = NULL): string
    {
        $message = addcslashes($message, self::ESCAPE_CHARS);
        $message = '"' . str_replace("\n", "\\n\"\n\"", $message) . '"';
        return ($prefix !== NULL ? $prefix . ' ' : '') . $message;
    }
}
