<?php
declare(strict_types=1);

namespace HelpPC\TranslationExtraction\Extractor\Filters;

use Nette\Utils\FileSystem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use HelpPC\TranslationExtraction\Extractor\Extractor;
use PhpParser;
use Latte;

class LatteFilter extends AFilter implements IFilter
{

    public function __construct()
    {
        $this->addFunction('_');
        $this->addFunction('!_');
        $this->addFunction('_n', 1, 2);
        $this->addFunction('!_n', 1, 2);
        $this->addFunction('_p', 2, NULL, 1);
        $this->addFunction('!_p', 2, NULL, 1);
        $this->addFunction('_np', 2, 3, 1);
        $this->addFunction('!_np', 2, 3, 1);
    }

    public function extract(string $file): array
    {
        $data = array();

        $latteParser = new Latte\Parser();
        $tokens = $latteParser->parse(FileSystem::read($file));

        $functions = array_keys($this->functions);
        usort($functions, static function (string $a, string $b) {
            return strlen($b) <=> strlen($a);
        });

        $phpParser = (new PhpParser\ParserFactory())->create(PhpParser\ParserFactory::PREFER_PHP7);
        foreach ($tokens as $token) {
            if ($token->type !== Latte\Token::MACRO_TAG) {
                continue;
            }

            $name = $this->findMacroName($token->text, $functions);
            if ($name === NULL) {
                continue;
            }
            $value = $this->trimMacroValue($name, $token->value);
            $stmts = $phpParser->parse("<?php\nf($value);");

            if ($stmts === NULL) {
                continue;
            }
            if ($stmts[0] instanceof Expression && $stmts[0]->expr instanceof FuncCall) {
                foreach ($this->functions[$name] as $definition) {
                    $message = $this->processFunction($definition, $stmts[0]->expr);
                    if ($message !== []) {
                        $message[Extractor::LINE] = $token->line;
                        $data[] = $message;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param array<string,int> $definition
     * @param FuncCall $node
     * @return array<string,string>
     */
    private function processFunction(array $definition, FuncCall $node): array
    {
        $message = [];
        foreach ($definition as $type => $position) {
            if (!isset($node->args[$position - 1])) {
                return [];
            }
            $arg = $node->args[$position - 1]->value;
            if ($arg instanceof String_) {
                $message[$type] = $arg->value;
            } else {
                return [];
            }
        }
        return $message;
    }

    /**
     * @param string $text
     * @param string[] $functions
     * @return string|null
     */
    private function findMacroName(string $text, array $functions): ?string
    {
        foreach ($functions as $function) {
            if (strpos($text, '{' . $function) === 0) {
                return $function;
            }
        }
        return NULL;
    }

    private function trimMacroValue(string $name, string $value): string
    {
        if (strpos($name, '!') === 0) {
            // exclamation mark is never removed
            return trim(substr($value, strlen($name)));
        }

        if (strpos($name, '_') === 0) {
            // only underscore is removed
            $offset = strlen(ltrim($name, '_'));
            return substr($value, $offset);
        }

        return $value;
    }
}
