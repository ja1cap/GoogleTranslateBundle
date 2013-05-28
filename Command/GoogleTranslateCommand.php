<?php
namespace Exercise\GoogleTranslateBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class GoogleTranslateCommand extends ContainerAwareCommand
{
    protected $progress;

    protected function configure()
    {
        $this
            ->setName('gtranslate:translate')
            ->setDefinition(array(
                new InputArgument('localeFrom', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('localeTo', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::REQUIRED, 'The bundle where to load the messages'),
            ))
            ->addOption('override', null, InputOption::VALUE_NONE, 'If set and file with locateTo exist - it will be replaced with new translated file')
            ->setDescription('translate message files in your project with Google Translate')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $foundBundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('bundle'));
        $basePath = $foundBundle->getPath().'/Resources/translations';

        if (!is_dir($basePath)) {
            throw new \Exception('Bundle has no translation message!');
        }

        $messagesFrom = Yaml::parse(sprintf("%s/messages.%s.yml", $basePath, $input->getArgument('localeFrom')));
        $messagesTo = Yaml::parse(sprintf("%s/messages.%s.yml", $basePath, $input->getArgument('localeTo')));

        //If override - translate all message again, even if it had been translated
        if ($input->getOption('override')) {
            $arrayDiff = $messagesFrom;
        } else {
            $arrayDiff = $this->arrayDiffKeyRecursive($messagesFrom, $messagesTo);
        }

        //If nothing to translate - exit
        if (!$count = count($arrayDiff, COUNT_RECURSIVE)) {
            $output->writeln('Nothing to translate!');
            exit;
        }

        $this->progress = $this->getHelperSet()->get('progress');
        $this->progress->start($output, $count);

        $translatedArray = $this->translateArray($arrayDiff, $input->getArgument('localeFrom'), $input->getArgument('localeTo'));

        if ($input->getOption('override') || !is_array($messagesTo)) {
            $messagesTo = $translatedArray;
        } else {
            $messagesTo = array_merge_recursive($translatedArray, $messagesTo);
        }

        $this->progress->finish();

        $output->writeln(sprintf('Creating "<info>%s</info>" file', 'messages.'.$input->getArgument('localeTo').'.yml'));

        $file = $basePath.'/messages.'.$input->getArgument('localeTo').'.yml';
        file_put_contents($file, Yaml::dump($messagesTo, 100500));

        $output->writeln('Translate is success!');
    }

    /**
     * Recursive translate array value message
     *
     * @param $array array
     * @param $langFrom string
     * @param $langTo string
     * @return array
     */
    public function translateArray($array, $langFrom, $langTo)
    {
        $translator = $this->getContainer()->get('exercise_google_translate.translator');

        foreach ($array as $key => $value) {

            if (is_array($value)) {
                $array[$key] = $this->translateArray($value, $langFrom, $langTo);
            } else {
                $array[$key] = $translator->translateString($value, $langFrom, $langTo);
            }

            $this->progress->advance();
        }

        return $array;
    }

    public function arrayDiffKeyRecursive($a1, $a2) {

        if (is_array($a2)) {
            $r = array_diff_key($a1, $a2);
        } else {

            return $a1;
        }

        foreach($a1 as $k => $v) {

            if (is_array($v)) {
                $r[$k] = $this->arrayDiffKeyRecursive($a1[$k], $a2[$k]);

                if (is_array($r[$k]) && count($r[$k]) == 0) {
                    unset($r[$k]);
                }
            }
        }

        return $r;
    }
}