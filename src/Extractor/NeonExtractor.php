<?php
declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor;

use Nette\Neon\Neon;
use Psr\Log\LoggerInterface;

class NeonExtractor extends Extractor
{
    public function __construct(?LoggerInterface $logger = NULL)
    {
        $this->addFilter('PHP', new Filters\PHPFilter());
        $this->addComment('Generated by Help PC Translation extractor');
        parent::__construct($logger);
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
        $output[] = '';
        $completeTree = [];
        foreach ($data as $message) {
            $messagePathTree = explode('.', $message[self::SINGULAR]);
            if (count($messagePathTree) < 2) {
                //continue;
            }
            $messageTree = $this->processPath($messagePathTree, $message[self::SINGULAR]);
            $completeTree = array_merge_recursive($messageTree, $completeTree);

        }

        return implode(PHP_EOL, $output) . PHP_EOL . Neon::encode($completeTree, Neon::BLOCK);
    }

    /**
     * @param string[] $itemparts
     * @param string $value
     * @return array|mixed[]
     */
    private function processPath(array $itemparts, string $value): array
    {
        $result = [];
        $part = 0;
        $last = &$result;
        for ($i = 0; $i < count($itemparts); $i++) {
            $part = $itemparts[$i];
            if ($i + 1 < count($itemparts))
                $last = &$last[$part];
            else
                $last[$part] = array();

        }
        $last[$part] = $value;

        return $result;
    }

    private const ESCAPE_CHARS = '"\\';

    protected function formatMessage(string $message, string $prefix = NULL): string
    {
        $message = addcslashes($message, self::ESCAPE_CHARS);
        $message = '"' . str_replace("\n", "\\n\"\n\"", $message) . '"';
        return ($prefix !== NULL ? $prefix . ' ' : '') . $message;
    }

}
