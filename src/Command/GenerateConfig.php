<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

final class GenerateConfig extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('generate:config')
            ->setDescription('Generates a YAML configuration file interactively.')
            ->setHelp('This command allows you to create a YAML config file by answering a few questions.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            throw new \LogicException('Expected QuestionHelper instance.');
        }
        $sources = [];

        $io->title('Generating GraphQL Type Generator Configuration');

        do {
            $io->section('Configuring a New Source');

            $question = new Question('<info>Enter the source name (e.g., AdminApi): </info>');
            $sourceName = $this->askString($helper, $input, $output, $question);

            $question = new Question(
                "<info>Enter the namespace for class generation: (e.g. Generated\\$sourceName): </info>",
                "Generated\\$sourceName"
            );
            $namespace = $this->askString($helper, $input, $output, $question);

            $question = new Question('<info>Enter the GraphQL endpoint URL: </info>');
            $url = $this->askString($helper, $input, $output, $question);

            $headers = [];
            $io->text('<comment>Enter headers (key: value pairs). Leave key empty to finish.</comment>');
            do {
                $question = new Question('<info>Enter a header key: </info>');
                $key = $this->askString($helper, $input, $output, $question);
                if ($key === '') {
                    break;
                }

                $question = new Question('<info>Enter the value for header "' . $key . '": </info>');
                $value = $this->askString($helper, $input, $output, $question);
                $headers[$key] = $value;
            } while (true);

            $sources[$sourceName] = [
                'namespace' => $namespace,
                'url' => $url,
                'headers' => $headers,
            ];

            $question = new Question('<info>Do you want to add another source? (yes/no): </info>', 'no');
            $addAnother = strtolower($this->askString($helper, $input, $output, $question));
        } while ($addAnother === 'yes');

        $yamlContent = Yaml::dump(['sources' => $sources]);

        do {
            $question = new Question(
                '<info>Enter the destination path for the YAML file (e.g., config/sources.yaml): </info>'
            );
            $destinationPath = $this->askString($helper, $input, $output, $question);

            if ($destinationPath !== '' && is_writable(dirname($destinationPath))) {
                break;
            } else {
                $io->error('The destination path is not writable. Please enter a valid path.');
            }
        } while (true);

        file_put_contents($destinationPath, $yamlContent);

        $io->success('YAML configuration file generated successfully at ' . $destinationPath);

        return Command::SUCCESS;
    }

    private function askString(
        QuestionHelper $helper,
        InputInterface $input,
        OutputInterface $output,
        Question $question
    ): string {
        $answer = $helper->ask($input, $output, $question);
        return is_string($answer) ? $answer : '';
    }
}
