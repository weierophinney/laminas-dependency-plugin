<?php

/**
 * @see       https://github.com/laminas/laminas-dependency-plugin for the canonical source repository
 * @copyright https://github.com/laminas/laminas-dependency-plugin/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-dependency-plugin/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\DependencyPlugin;

use Composer\Composer;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PreCommandRunEvent;

use function array_map;
use function array_shift;
use function get_class;
use function in_array;
use function preg_split;
use function reset;
use function sprintf;

abstract class AbstractDependencyRewriter implements RewriterInterface
{
    /** @var Composer */
    protected $composer;

    /** @var IOInterface */
    protected $io;

    /**
     * @var Replacements
     */
    private $replacements;

    public function __construct()
    {
        $this->replacements = new Replacements();
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->output(sprintf('<info>Activating %s</info>', get_class($this)), IOInterface::DEBUG);
    }

    /**
     * When a ZF package is requested, replace with the Laminas variant.
     *
     * When a `require` operation is requested, and a ZF package is detected,
     * this listener will replace the argument with the equivalent Laminas
     * package. This ensures that the `composer.json` file is written to
     * reflect the package installed.
     */
    public function onPreCommandRun(PreCommandRunEvent $event)
    {
        $this->output(
            sprintf(
                '<info>In %s::%s</info>',
                get_class($this),
                __FUNCTION__
            ),
            IOInterface::DEBUG
        );

        if (! in_array($event->getCommand(), ['require', 'update'], true)) {
            // Nothing to do here.
            return;
        }

        $input = $event->getInput();
        if (! $input->hasArgument('packages')) {
            return;
        }

        $input->setArgument(
            'packages',
            array_map([$this, 'updatePackageArgument'], $input->getArgument('packages'))
        );
    }

    abstract public function onPrePackageInstallOrUpdate(PackageEvent $event);

    /**
     * @param string $message
     * @param int $verbosity
     */
    protected function output($message, $verbosity = IOInterface::NORMAL)
    {
        $this->io->write($message, true, $verbosity);
    }

    /**
     * Parses a package argument from the command line, replacing it with the
     * Laminas variant if it exists.
     *
     * @param string $package Package specification from command line
     * @return string Modified package specification containing Laminas
     *     substitution, or original if no changes required.
     */
    private function updatePackageArgument($package)
    {
        $result = preg_split('/[ :=]/', $package, 2);
        if ($result === false) {
            return $package;
        }
        $name = array_shift($result);

        if (! $this->isZendPackage($name)) {
            return $package;
        }

        $replacementName = $this->transformPackageName($name);
        if ($replacementName === $name) {
            return $package;
        }

        $this->io->write(
            sprintf(
                'Changing package in current command from %s to %s',
                $name,
                $replacementName
            ),
            true,
            IOInterface::DEBUG
        );

        $version = reset($result);

        if ($version === false) {
            return $replacementName;
        }

        return sprintf('%s:%s', $replacementName, $version);
    }

    /**
     * @param string $name
     * @return bool
     */
    protected function isZendPackage($name)
    {
        return $this->replacements->isZendPackage($name);
    }

    /**
     * @param string $name
     * @return string
     */
    protected function transformPackageName($name)
    {
        return $this->replacements->transformPackageName($name);
    }
}
