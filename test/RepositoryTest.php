<?php

namespace Drupal\ParseComposer;

use Symfony\Component\Process\ExecutableFinder;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Composer\IO\NullIO;
use Composer\Config;

/**
 * Copied from Composer\Test\Repository\VcsRepositoryTest
 */

class RepositoryTest extends \PHPUnit_Framework_TestCase
{
    private static $composerHome;
    private static $gitRepoDir;
    private $skipped;

    protected function initialize()
    {
        self::$composerHome = sys_get_temp_dir() . '/composer-home-'.mt_rand().'/';
        self::$gitRepoDir = __DIR__ . '/../res';
    }

    public function setUp()
    {
        $this->initialize();

        if ($this->skipped) {
            $this->markTestSkipped($this->skipped);
        }
        $this->dumper = new ArrayDumper();
    }

    public function testLoadVersions()
    {
        $projects = array(
            'omega' => array(
                '7.4.2' => array(
                    'type' => 'drupal-theme'
                ),
                '7.3.1' => true,
                'dev-7.x-4.x' => true,
            ),
            'flood_sem' => array(
                'dev-7.x-1.x' => array(
                    'type' => 'drupal-module'
                )
            ),
            'config_devel' => array(
                'dev-8.x-1.x' => array(
                    'type' => 'drupal-module'
                ),
            ),
            'coder' => array(
                'dev-8.x-2.x' => array(
                    'type' => 'library'
                ),
            ),
            'panels' => array(
                'dev-8.x-3.x' => array(
                    'type' => 'drupal-module'
                ),
            ),
            'drupal' => array(
                '7.x-dev' => [
                    'type' => 'drupal-core'
                ],
                '7.43.0' => [
                    'type' => 'drupal-core'
                ],
                '8.0.x-dev' => [
                    'type' => 'drupal-core'
                ],
                '8.1.x-dev' => [
                    'type' => 'drupal-core'
                ],
                '8.2.x-dev' => [
                    'type' => 'drupal-core'
                ],
                '8.0.5' => [
                    'type' => 'drupal-core'
                ],
                '8.1.0-beta1' => [
                    'type' => 'drupal-core'
                ],
                '8.1.0-beta2' => [
                    'type' => 'drupal-core'
                ],
            ),
             'search_api_solr' => [
                 '8.1.0-alpha4' => [
                     'require' => [
                        'drupal/search_api' => '8.*',
                        'drupal/core' => '8.*',
                        'solarium/solarium' => '3.5.1'
                     ]
                 ],
             ],
             'df' => [
                 '8.1.0-beta6' => [
                     'require' => [
                         'drupal/crop' => '8.1.0',
                         'desandro/masonry' => '3.3.1',
                         'drupal/address' => '8.1.0-beta3',
                         'cweagans/composer-patches' => '^1.5.0',
                         'drupal/core' => '8.*'
                     ]
                 ],
             ]
        );

        $config = new Config();
        $config->merge(array(
            'config' => array(
                'home' => self::$composerHome,
            ),
        ));
        foreach ($projects as $name => $expected) {
            $repo = new Repository(
                array('url' => self::$gitRepoDir.'/'.$name, 'type' => 'vcs'),
                new NullIO,
                $config
            );
            /** @var \Composer\Package\PackageInterface[] $packages */
            $packages = $repo->getPackages();

            // Make sure that development versions do not get a distribution
            // archive URL because Drupal.org does not produce archives for
            // each commit.
            // If the latest commit in a development branch happens to
            // correspond to a tagged release the package does (and
            // should!) receive a distribution archive URL. To make sure the
            // test does not fail in this case we track the source
            // references of all non-development versions.
            /** @var \Composer\Package\PackageInterface[] $developmentPackages */
            $developmentPackages = [];
            $sourceReferences = [];
            foreach ($packages as $package) {
                if (substr($package->getVersion(), -2) === '.x') {
                    $developmentPackages[] = $package;
                } else {
                    $sourceReferences += [$package->getName() => []];
                    $sourceReferences[$package->getName()][] = $package->getSourceReference();
                }
            }

            foreach ($developmentPackages as $package) {
                if (isset($sourceReferences[$package->getName()]) && in_array($package->getSourceReference(), $sourceReferences[$package->getName()], true)) {
                    continue;
                }
                $this->assertEquals('', $package->getDistUrl());
            }

            foreach ($packages as $package) {
                if (isset($expected[$package->getPrettyVersion()])) {
                    if (is_array($p = $expected[$package->getPrettyVersion()])) {
                        $require = isset($p['require']) ? $p['require'] : [];
                        unset($p['require']);
                        foreach ($require as $package_name => $version) {
                            $this->assertEquals($version, $package->getRequires()[$package_name]->getPrettyConstraint());
                        }
                        $this->assertEmpty(array_diff_assoc(
                            $p, $this->dumper->dump($package)
                        ));
                    }
                    $this->assertEquals($package->getPrettyName(), 'drupal/'.$name);
                    unset($expected[$package->getPrettyVersion()]);
                }
            }
            $this->assertEmpty($expected, 'Missing versions: '.implode(', ', array_keys($expected)));
        }

    }
}
