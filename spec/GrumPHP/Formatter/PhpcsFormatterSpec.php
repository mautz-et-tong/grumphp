<?php

namespace spec\GrumPHP\Formatter;

use GrumPHP\Collection\ProcessArgumentsCollection;
use GrumPHP\Formatter\PhpcsFormatter;
use GrumPHP\Process\ProcessBuilder;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Process\Process;

/**
 * @mixin PhpcsFormatter
 */
class PhpcsFormatterSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('GrumPHP\Formatter\PhpcsFormatter');
    }

    function it_is_a_process_formatter()
    {
        $this->shouldHaveType('GrumPHP\Formatter\ProcessFormatterInterface');
    }

    function it_handles_command_exceptions(Process $process)
    {
        $process->getOutput()->willReturn('');
        $process->getErrorOutput()->willReturn('stderr');
        $this->format($process)->shouldReturn('stderr');
    }

    function it_handles_invalid_json(Process $process)
    {
        $process->getOutput()->willReturn('invalid');
        $this->format($process)->shouldReturn('invalid');
    }

    function it_formats_phpcs_json_output_for_single_file(Process $process, ProcessBuilder $processBuilder)
    {
        $json = $this->parseJson(array(
            '/filePath' => array('messages' => array(array('fixable' => true),),),
        ));

        $arguments = new ProcessArgumentsCollection();
        $process->getOutput()->willReturn('something' . PHP_EOL . 'something' . $json);
        $this->format($process)->shouldBe('something' . PHP_EOL . 'something');

        $this->getSuggestedFilesFromJson(json_decode($json, true))->shouldBe(array('/filePath'));

        $processBuilder->buildProcess($arguments)->willReturn($process);
        $process->getCommandLine()->shouldBeCalled();
        $this->formatErrorMessage($arguments, $processBuilder)
            ->shouldBe(sprintf(
                '%sYou can fix some errors by running following command:%s',
                PHP_EOL . PHP_EOL,
                PHP_EOL . ''
            ));
    }

    function it_formats_phpcs_json_output_for_multiple_files(Process $process, ProcessBuilder $processBuilder)
    {
        $json = $this->parseJson(array(
            '/filePath' => array('messages' => array(array('fixable' => true),),),
            '/filePath2' => array('messages' => array(array('fixable' => false),),),
        ));

        $arguments = new ProcessArgumentsCollection(array('phpcbf'));
        $process->getOutput()->willReturn('something' . PHP_EOL . 'something' . $json);
        $this->format($process)->shouldBe('something' . PHP_EOL . 'something');

        $this->getSuggestedFilesFromJson(json_decode($json, true))->shouldBe(array('/filePath'));

        $processBuilder->buildProcess($arguments)->willReturn($process);
        $process->getCommandLine()->willReturn();
        $this->formatErrorMessage($arguments, $processBuilder)
            ->shouldBe(sprintf(
                '%sYou can fix some errors by running following command:%s',
                PHP_EOL . PHP_EOL,
                PHP_EOL . ''
            ));
    }

    /**
     * @param $files
     *
     * @return string
     */
    private function parseJson(array $files)
    {
        $fixable = 0;
        foreach ($files as $file) {
            foreach ($file['messages'] as $message) {
                if ($message['fixable']) {
                    $fixable++;
                    break;
                }
            }
        }
        return PHP_EOL . json_encode(array(
            'totals' => array(
                'fixable' => $fixable
            ),
            'files' => $files
        ));
    }
}
