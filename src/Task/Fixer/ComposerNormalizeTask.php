<?php

namespace Acquia\Orca\Task\Fixer;

use Acquia\Orca\Exception\TaskFailureException;
use Acquia\Orca\Task\TaskBase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Normalizes composer.json files.
 */
class ComposerNormalizeTask extends TaskBase {

  /**
   * Whether or not there have been any failures.
   *
   * @var bool
   */
  private $failures = FALSE;

  /**
   * {@inheritdoc}
   */
  public function statusMessage(): string {
    return 'Normalizing composer.json files';
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach ($this->getFiles() as $file) {
      $path = realpath($file->getPathname());
      $this->normalize($path);
    }
    if ($this->failures) {
      throw new TaskFailureException();
    }
  }

  /**
   * Finds all composer.json files.
   *
   * @return \Symfony\Component\Finder\Finder
   *   A Finder query for all module info files.
   */
  private function getFiles() {
    return (new Finder())
      ->files()
      ->followLinks()
      ->in($this->getPath())
      ->notPath(['tests', 'vendor'])
      ->name(['composer.json']);
  }

  /**
   * Normalizes the composer.json file.
   *
   * @param string $path
   *   The absolute path to the file.
   */
  private function normalize(string $path): void {
    try {
      $this->processRunner->runOrcaVendorBin([
        'composer',
        '--ansi',
        'normalize',
        '--indent-size=4',
        '--indent-style=space',
        $path,
        // The cwd must be the ORCA project directory in order for Composer to
        // find the "normalize" command.
      ], $this->projectDir);
    }
    catch (ProcessFailedException $e) {
      $this->failures = TRUE;
    }
  }

}
