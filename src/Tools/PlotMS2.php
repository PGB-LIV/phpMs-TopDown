<?php
namespace pgb_liv\top_down\Tools;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use pgb_liv\top_down\Command\PlotMS2Command;

class PlotMS2 extends Application
{

    /**
     * Gets the name of the command based on input.
     *
     * @param InputInterface $input
     *            The input interface
     *            
     * @return string The command name
     */
    protected function getCommandName(InputInterface $input)
    {
        // This should return the name of your command.
        return 'PlotMS2';
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        // Keep the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new PlotMS2Command();

        return $defaultCommands;
    }

    /**
     * Overridden so that the application doesn't expect the command
     * name to be the first argument.
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        // clear out the normal first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}