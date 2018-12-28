<?php

namespace Acquia\Orca\Fixture;

use Acquia\Orca\Utility\ConfigLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Provide access to Acquia Drupal submodules physically in the fixture.
 */
class SubmoduleManager {

  /**
   * The config loader.
   *
   * @var \Acquia\Orca\Utility\ConfigLoader
   */
  private $configLoader;

  /**
   * The filesystem.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private $filesystem;

  /**
   * The finder.
   *
   * @var \Symfony\Component\Finder\Finder
   */
  private $finder;

  /**
   * The fixture.
   *
   * @var \Acquia\Orca\Fixture\Fixture
   */
  private $fixture;

  /**
   * The top-level Acquia packages.
   *
   * @var \Acquia\Orca\Fixture\Package[]
   */
  private $topLevelPackages;

  /**
   * The submodules found in the fixture.
   *
   * @var \Acquia\Orca\Fixture\Package[]
   */
  private $submodules = [];

  /**
   * Constructs an instance.
   *
   * @param \Acquia\Orca\Utility\ConfigLoader $config_loader
   *   The config loader.
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   * @param \Symfony\Component\Finder\Finder $finder
   *   The finder.
   * @param \Acquia\Orca\Fixture\Fixture $fixture
   *   The fixture.
   * @param \Acquia\Orca\Fixture\PackageManager $package_manager
   *   The package manager.
   */
  public function __construct(ConfigLoader $config_loader, Filesystem $filesystem, Finder $finder, Fixture $fixture, PackageManager $package_manager) {
    $this->configLoader = $config_loader;
    $this->filesystem = $filesystem;
    $this->finder = $finder;
    $this->fixture = $fixture;
    $this->topLevelPackages = $package_manager->getMultiple();
  }

  /**
   * Gets an array of all Acquia submodules.
   *
   * @return \Acquia\Orca\Fixture\Package[]
   */
  public function getAll(): array {
    if ($this->submodules) {
      return $this->submodules;
    }
    $paths = $this->getAllPackageInstallPaths();
    $this->submodules = $this->getInPaths($paths);
    return $this->submodules;
  }

  /**
   * Gets an array of submodules of a given parent.
   *
   * @param \Acquia\Orca\Fixture\Package $package
   *   The package to search for submodules.
   *
   * @return \Acquia\Orca\Fixture\Package[]
   */
  public function getByParent(Package $package): array {
    $paths = [$package->getInstallPathAbsolute()];
    return $this->getInPaths($paths);
  }

  /**
   * Gets an array of submodules in a given set of paths.
   *
   * @param string[] $paths
   *   The paths to search for submodules.
   *
   * @return \Acquia\Orca\Fixture\Package[]
   */
  public function getInPaths(array $paths): array {
    $submodules = [];
    foreach ($this->findSubmoduleComposerJsonFiles($paths) as $file) {
      $config = $this->configLoader->load($file->getPathname());
      $install_path = str_replace("{$this->fixture->getPath()}/", '', $file->getPath());
      $package_data = [
        'name' => $config->get('name'),
        'install_path' => $install_path,
        'url' => $file->getPath(),
        'version' => '@dev',
      ];
      $submodules[$config->get('name')] = new Package($this->fixture, $package_data);
    }
    return $submodules;
  }

  /**
   * Gets an array of package install paths.
   *
   * @return array
   */
  private function getAllPackageInstallPaths(): array {
    $paths = [];
    foreach ($this->topLevelPackages as $package) {
      $path = $package->getInstallPathAbsolute();
      if ($this->filesystem->exists($path)) {
        $paths[] = $path;
      }
    }
    return $paths;
  }

  /**
   * Finds all Acquia Drupal submodule composer.json files.
   *
   * @param string[] $paths
   *   An array of paths to recursively search for submodules.
   *
   * @return \Symfony\Component\Finder\Finder|array
   */
  private function findSubmoduleComposerJsonFiles(array $paths) {
    if (!$paths) {
      return [];
    }
    return $this->finder
      ->files()
      ->followLinks()
      ->in($paths)
      ->notPath('vendor')
      ->name('composer.json')
      ->filter(function (\SplFileInfo $file) {
        return $this->isSubmoduleComposerJson($file);
      });
  }

  /**
   * Determines whether a given composer.json file belongs to a submodule.
   *
   * @param \SplFileInfo $file
   *   The file to examine.
   *
   * @return bool
   */
  private function isSubmoduleComposerJson(\SplFileInfo $file): bool {
    // Ignore files that don't exist. (It is unknown why the Finder search
    // returns non-existent files to begin with, but it does.)
    if (!$this->filesystem->exists($file->getPathname())) {
      return FALSE;
    }

    try {
      $config = $this->configLoader->load($file->getPathname());
      list($vendor_name, $package_name) = explode('/', $config->get('name'));
    }
    catch (\Exception $e) {
      return FALSE;
    }

    // Ignore top level packages.
    if (in_array($config->get('name'), array_keys($this->topLevelPackages))) {
      return FALSE;
    }

    // Ignore everything but Drupal modules.
    if ($config->get('type') !== 'drupal-module') {
      return FALSE;
    }

    // Ignore modules that explicitly opt out of installation.
    if ($config->get('extra.orca.install', TRUE) === FALSE) {
      return FALSE;
    }

    // Ignore modules that aren't under the "drupal" vendor name.
    if ($vendor_name !== 'drupal') {
      return FALSE;
    }

    // Ignore modules without a corresponding .info.yml file.
    $info_yml_file = "{$file->getPath()}/{$package_name}.info.yml";
    if (!$this->filesystem->exists($info_yml_file)) {
      return FALSE;
    }

    return TRUE;
  }

}
