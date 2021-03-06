<?php
namespace Exercise\GoogleTranslateBundle;

class ParametersEscaper
{
    /** @var \ArrayObject */
    protected $parametersArray;

    /** @var  \ArrayIterator */
    protected $iterator;

    public function escapeParameters($string)
    {
        $this->parametersArray = new \ArrayObject();

        return preg_replace_callback(
            "|%[\S]*%|",
            array($this, 'escapeParametersCallback'),
            $string);
    }

    public function unEscapeParameters($string)
    {
        if (!$this->parametersArray) {
            throw new \Exception('You try unescape string that not be escaped');
        }

        $this->iterator = $this->parametersArray->getIterator();

        return preg_replace_callback(
            "|NotTranslatedString|",
            array($this, 'unEscapeParametersCallback'),
            $string);
    }

    private function escapeParametersCallback($matches)
    {
        $this->parametersArray->append($matches['0']);

        return 'NotTranslatedString';
    }

    private function unEscapeParametersCallback($matches)
    {
        $value = $this->iterator->current();
        $this->iterator->next();

        return $value;
    }
}
