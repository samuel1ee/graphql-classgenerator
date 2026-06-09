<?php

namespace Aksonov\GraphqlGenerator\Command;

use Symfony\Component\Console\Command\Command;
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
            ->setName('generate:config') // Setting the command name
            ->setDescription('Generates a YAML configuration file interactively.')
            ->setHelp('This command allows you to create a YAML config file by answering a few questions.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');
        $sources = [];

        $io->title('Generating GraphQL Type Generator Configuration');

        do {
            $io->section('Configuring a New Source');

            // Ask for the source name
            $question = new Question('<info>Enter the source name (e.g., AdminApi): </info>');
            $sourceName = $helper->ask($input, $output, $question);

            // Ask for the namespace
            $question = new Question(
                "<info>Enter the namespace for class generation: (e.g. Generated\\$sourceName): </info>",
                "Generated\\$sourceName"
            );
            $namespace = $helper->ask($input, $output, $question);

            // Ask for the GraphQL endpoint
            $question = new Question('<info>Enter the GraphQL endpoint URL: </info>');
            $url = $helper->ask($input, $output, $question);

            // Ask for headers
            $headers = [];
            $io->text('<comment>Enter headers (key: value pairs). Leave key empty to finish.</comment>');
            do {
                $question = new Question('<info>Enter a header key: </info>');
                $key = $helper->ask($input, $output, $question);
                if (!$key) {
                    break;
                }

                $question = new Question('<info>Enter the value for header "' . $key . '": </info>');
                $value = $helper->ask($input, $output, $question);
                $headers[$key] = $value;
            } while (true);

            // Add the source to the sources array
            $sources[$sourceName] = [
                'namespace' => $namespace,
                'url' => $url,
                'headers' => $headers,
            ];

            // Ask if the user wants to add another source
            $question = new Question('<info>Do you want to add another source? (yes/no): </info>', 'no');
            $addAnother = strtolower($helper->ask($input, $output, $question));
        } while ($addAnother === 'yes');

        // Generate the YAML content
        $yamlContent = Yaml::dump(['sources' => $sources]);

        // Ask for the destination path
        do {
            $question = new Question(
                '<info>Enter the destination path for the YAML file (e.g., config/sources.yaml): </info>'
            );
            $destinationPath = $helper->ask($input, $output, $question);

            if ($destinationPath && is_writable(dirname($destinationPath))) {
                break;
            } else {
                $io->error('The destination path is not writable. Please enter a valid path.');
            }
        } while (true);

        // Save the YAML content to a file
        file_put_contents($destinationPath, $yamlContent);

        $io->success('YAML configuration file generated successfully at ' . $destinationPath);

        return Command::SUCCESS;
    }
}
