<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

/**
 * This class is used to create a release of PrestaShop,
 * see ReleaseCreator::createRelease() function.
 */
class ReleaseCreator
{
    /**
     * Path where the releases will be stored.
     *
     * @var string
     */
    const RELEASES_DIR_RELATIVE_PATH = 'tools/build/releases';

    /**
     * Name of the release's zip.
     *
     * @var string
     */
    const INSTALLER_ZIP_FILENAME = 'prestashop.zip';

    /**
     * Directory's name of the prestashop release in creation.
     * Deleted at the end.
     *
     * @var string
     */
    const PRESTASHOP_TMP_DIR = 'prestashop';

    /**
     * Use to write on terminal.
     *
     * @var ConsoleWriter
     */
    protected $consoleWriter;

    /**
     * Line separator used for all messages.
     *
     * @var string
     */
    protected $lineSeparator = PHP_EOL;

    /**
     * Files to remove.
     *
     * @var array
     */
    protected $filesRemoveList = [
        '.php-cs-fixer.dist.php',
        '.DS_Store',
        '.gitignore',
        '.gitmodules',
        '.travis.yml',
        'package-lock.json',
        '.babelrc',
        'postcss.config.js',
    ];

    /**
     * Folders to remove.
     *
     * @var array
     */
    protected $foldersRemoveList = [];

    /**
     * Pattern of files or directories to remove.
     *
     * @var array
     */
    protected $patternsRemoveList = [
        'tools/contrib$',
        'travis\-scripts$',
        'CONTRIBUTING\.md$',
        'composer\.json$',
        'diff\-hooks\.php',
        '(.*)?\.composer$',
        '.*\.map$',
        '.*\.psd$',
        '.*\.md$',
        '.*\.rst$',
        '.*phpunit(.*)?',
        '(.*)?\.travis\.',
        '.*\.DS_Store$',
        '.*\.eslintrc$',
        '.*\.editorconfig$',
        'web/.*$',
        'app/config/parameters\.yml$',
        'app/config/parameters\.php$',
        'config/settings\.inc\.php$',
        'app/cache/..*$',
        '\.t9n\.yml$',
        '\.scrutinizer\.yml$',
        'admin/(.*/)?webpack\.config\.js$',
        'admin/(.*/)?package\.json$',
        'admin/(.*/)?bower\.json$',
        'admin/(.*/)?config\.rb$',
        'admin/themes/default/sass$',
        //'admin/themes/new\-theme/js$',
        'admin/themes/new\-theme/scss$',
        'themes/_core$',
        'themes/classic/_dev',
        'themes/webpack\.config\.js$',
        'themes/package\.json$',
        'vendor\/[a-zA-Z0-0_-]+\/[a-zA-Z0-0_-]+\/[Tt]ests?$',
        'vendor/tecnickcom/tcpdf/examples$',
        'app/cache/..*$',
        '.idea',
        'tools/build$',
        'tools/foreignkeyGenerator$',
        '.*node_modules.*',
        '\.eslintignore$',
        '\.eslintrc\.js$',
        '\.php_cs\.dist$',
        'tools/assets$',
        '\.webpack$',
    ];

    /**
     * Contains all files and directories of the PrestaShop release.
     *
     * @var array
     */
    protected $filesList = [];

    /**
     * Absolute path of the temp PrestaShop release.
     *
     * @var string
     */
    protected $tempProjectPath;

    /**
     * Absolute path of the current user's PrestaShop (root path).
     *
     * @var string
     */
    protected $projectPath;

    /**
     * Release version which user wants.
     *
     * @var string
     */
    protected $version;

    /**
     * Do we include the installer?
     * Do not work with $useZip = false.
     *
     * @var bool
     */
    protected $useInstaller;

    /**
     * Do we zip the release?
     *
     * @var bool
     */
    protected $useZip;

    /**
     * Consisting of prestashop_ and the version. e.g prestashop_1.7.3.4.zip
     *
     * @var string
     */
    protected $zipFileName;

    /**
     * Where the release will be moved when done.
     *
     * @var string
     */
    protected $destinationDir;

    /**
     * Set the release wanted version, and some options.
     *
     * @param string|null $version
     * @param bool $useInstaller
     * @param bool $useZip
     * @param string $destinationDir
     * @param bool $keepTests
     */
    public function __construct(?string $version = null, bool $useInstaller = true, bool $useZip = true, string $destinationDir = '', bool $keepTests = false)
    {
        $this->consoleWriter = new ConsoleWriter();
        $tmpDir = sys_get_temp_dir();
        $prestashopTmpDir = self::PRESTASHOP_TMP_DIR;
        $this->tempProjectPath = "{$tmpDir}/$prestashopTmpDir";
        $this->consoleWriter->displayText(
            "--- Temp dir used will be '{$tmpDir}'{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );
        $this->projectPath = realpath(__DIR__ . '/../../..');
        $this->version = $version ? $version : $this->getCurrentVersion();
        $this->zipFileName = "prestashop_$this->version.zip";
        // Keep files for tests (tests, git and docker folders)
        if (!$keepTests) {
            $this->patternsRemoveList[] = 'tests(\-legacy)?$';
            $this->patternsRemoveList[] = '(.*)?\.git(.*)?$';
            $this->patternsRemoveList[] = '.docker';
            $this->patternsRemoveList[] = 'docker-compose\.yml$';
            $this->patternsRemoveList[] = '((?<!_dev\/)package\.json)$';
        }

        if (empty($this->version)) {
            throw new Exception('Version is not provided and cannot be found in project.');
        }

        if (empty($destinationDir)) {
            $releasesDir = self::RELEASES_DIR_RELATIVE_PATH;
            $reference = $this->version . "_" . date("Ymd_His");
            $destinationDir = "{$this->projectPath}/$releasesDir/$reference";
        }
        $this->destinationDir = $destinationDir;
        $this->consoleWriter->displayText(
            "--- Destination dir used will be '{$this->destinationDir}'{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );
        $this->useZip = $useZip;
        $this->useInstaller = $useInstaller;

        if ($this->useZip && $this->useInstaller) {
            $this->consoleWriter->displayText(
                "--- Release will have the installer and will be zipped.{$this->lineSeparator}",
                ConsoleWriter::COLOR_GREEN
            );
        } elseif ($this->useZip) {
            $this->consoleWriter->displayText(
                "--- Release will be zipped.{$this->lineSeparator}",
                ConsoleWriter::COLOR_GREEN
            );
        } else {
            $this->consoleWriter->displayText(
                "--- Release will be a folder without installer.{$this->lineSeparator}",
                ConsoleWriter::COLOR_GREEN
            );
        }
    }

    /**
     * Create a new release.
     *
     * @return $this
     * @throws BuildException
     */
    public function createRelease()
    {
        if (!file_exists($this->destinationDir) && !mkdir($this->destinationDir, 0777, true)) {
            throw new BuildException("ERROR: can not create directory '{$this->destinationDir}'");
        }
        $startTime = date('H:i:s');
        $this->consoleWriter->displayText(
            "--- Script started at {$startTime}{$this->lineSeparator}{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );
        $this->createTmpProjectDir()
            ->setFilesConstants()
            ->setupShopVersion()
            ->generateLicensesFile()
            ->generateCachedirFiles()
            ->runComposerInstall()
            ->runBuildAssets()
            ->createPackage();
        $endTime = date('H:i:s');
        $this->consoleWriter->displayText(
            "{$this->lineSeparator}--- Script ended at {$endTime}{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );

        if ($this->useZip) {
            $argReleaseZipFilePath = escapeshellarg("{$this->destinationDir}/{$this->zipFileName}");
            $releaseSize = exec("du -hs {$argReleaseZipFilePath} | cut -f1");
        } else {
            $argReleaseDirectoryPath = escapeshellarg("{$this->destinationDir}");
            $releaseSize = exec("du -hs {$argReleaseDirectoryPath} | cut -f1");
        }
        $this->consoleWriter->displayText(
            "--- Release size: {$releaseSize}{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );

        return $this;
    }

    /**
     * Copy current user PrestaShop dir to a tmp directory
     * where we'll clean it for the release.
     *
     * @return $this
     */
    protected function createTmpProjectDir()
    {
        $this->consoleWriter->displayText("Copy project in {$this->tempProjectPath}...", ConsoleWriter::COLOR_YELLOW);
        $argProjectPath = escapeshellarg($this->projectPath);
        $argTmpDestination = escapeshellarg("{$this->tempProjectPath}");

        if (file_exists("{$this->tempProjectPath}")) {
            exec("rm -rf $argTmpDestination");
        }
        exec("mkdir $argTmpDestination && \
            cd {$argProjectPath} && \
            git archive HEAD | tar -xC {$argTmpDestination} && \
            cd -");
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Define all constants of the project to the desired version.
     *
     * @return $this
     */
    protected function setFilesConstants()
    {
        $this->consoleWriter->displayText("Setting files constants...", ConsoleWriter::COLOR_YELLOW);
        $this->setConfigDefinesConstants()
            ->setInstallDevConfigurationConstants()
            ->setInstallDevInstallVersionConstants();
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Define all config/defines.inc.php constants to the desired version.
     *
     * @return $this
     * @throws BuildException
     */
    protected function setConfigDefinesConstants()
    {
        $configDefinesPath = $this->tempProjectPath . '/config/defines.inc.php';
        $configDefinesContent = file_get_contents($configDefinesPath);
        $configDefinesNewContent = preg_replace('/(.*(define).*)["\']_PS_MODE_DEV_["\'](.*);/Ui', 'define(\'_PS_MODE_DEV_\', false);', $configDefinesContent);
        $configDefinesNewContent = preg_replace('/(.*)["\']_PS_DISPLAY_COMPATIBILITY_WARNING_["\'](.*);/Ui', 'define(\'_PS_DISPLAY_COMPATIBILITY_WARNING_\', false);', $configDefinesNewContent);

        if (!file_put_contents($configDefinesPath, $configDefinesNewContent)) {
            throw new BuildException("Unable to update contents of '$configDefinesPath'");
        }

        return $this;
    }

    /**
     * Get the current version in the project
     *
     * @return string PrestaShop version
     */
    protected function getCurrentVersion()
    {
        require_once $this->projectPath.'/src/Core/Version.php';
        return \PrestaShop\PrestaShop\Core\Version::VERSION;
    }

    /**
     * Define the PrestaShop version to the desired version.
     *
     * @return self
     * @throws BuildException
     */
    protected function setupShopVersion()
    {
        $kernelFile = $this->tempProjectPath.'/app/AppKernel.php';
        $version = new Version($this->version);

        $kernelFileContent = file_get_contents($kernelFile);
        $kernelFileContent = preg_replace(
            '~const VERSION = \'(.*)\';~',
            "const VERSION = '".$version->getVersion()."';",
            $kernelFileContent
        );
        $kernelFileContent = preg_replace(
            '~const MAJOR_VERSION_STRING = \'(.*)\';~',
            "const MAJOR_VERSION_STRING = '".$version->getMajorVersionString()."';",
            $kernelFileContent
        );
        $kernelFileContent = preg_replace(
            '~const MAJOR_VERSION = (.*);~',
            "const MAJOR_VERSION = ".$version->getMajorVersion().";",
            $kernelFileContent
        );
        $kernelFileContent = preg_replace(
            '~const MINOR_VERSION = (.*);~',
            "const MINOR_VERSION = ".$version->getMinorVersion().";",
            $kernelFileContent
        );
        $kernelFileContent = preg_replace(
            '~const RELEASE_VERSION = (.*);~',
            "const RELEASE_VERSION = ".$version->getReleaseVersion().";",
            $kernelFileContent
        );

        if (!file_put_contents($kernelFile, $kernelFileContent)) {
            throw new BuildException("Unable to update contents of $kernelFile.");
        }

        return $this;
    }

    /**
     * Define all install-dev/data/xml/configuration.xml constants to the desired version.
     *
     * @return $this
     * @throws BuildException
     */
    protected function setInstallDevConfigurationConstants()
    {
        $configPath = $this->tempProjectPath.'/install-dev/data/xml/configuration.xml';

        if (file_exists($configPath)) {
            $configPathContent = file_get_contents($configPath);
            $configPathNewContent = preg_replace('/name="PS_SMARTY_FORCE_COMPILE"(.*?)value>([\d]*)/si', 'name="PS_SMARTY_FORCE_COMPILE"$1value>0', $configPathContent);
            $configPathNewContent = preg_replace('/name="PS_SMARTY_CONSOLE"(.*?)value>([\d]*)/si', 'name="PS_SMARTY_CONSOLE"$1value>0', $configPathNewContent);

            if (!file_put_contents($configPath, $configPathNewContent)) {
                throw new BuildException("Unable to update contents of '$configPath'.");
            }
        }

        return $this;
    }

    /**
     * Define all install-dev/install_version.php constants to the desired version.
     *
     * @return $this
     * @throws BuildException
     */
    protected function setInstallDevInstallVersionConstants()
    {
        $installVersionPath = $this->tempProjectPath . '/install-dev/install_version.php';
        $installVersionContent = file_get_contents($installVersionPath);
        $installVersionNewContent = preg_replace('#_PS_INSTALL_VERSION_\', \'(.*)\'\)#', '_PS_INSTALL_VERSION_\', \'' . $this->version . '\')', $installVersionContent);

        if (!file_put_contents($installVersionPath, $installVersionNewContent)) {
            throw new BuildException("Unable to update contents of '$installVersionPath'.");
        }

        return $this;
    }

    /**
     * Generate the /LICENCES file. Concatenate all text files which contains the 'licence' word
     * in their filename into this unique one.
     *
     * @return $this
     * @throws BuildException
     */
    protected function generateLicensesFile()
    {
        $this->consoleWriter->displayText("Generating licences file...", ConsoleWriter::COLOR_YELLOW);
        $content = null;
        $directory = new \RecursiveDirectoryIterator($this->tempProjectPath);
        $iterator = new \RecursiveIteratorIterator($directory);
        $regex = new \RegexIterator($iterator, '/^.*\/.*license(\.txt)?$/i', \RecursiveRegexIterator::GET_MATCH);

        foreach($regex as $file => $value) {
            $content .= file_get_contents($file) . "\r\n\r\n";
        }

        if (!file_put_contents($this->tempProjectPath . '/LICENSES', $content)) {
            throw new BuildException('Unable to create LICENSES file.');
        }
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Generate CACHEDIR.TAG files in some locations. This file is used in many applications
     * to exclude directories from backups.
     *
     * @return $this
     * @throws BuildException
     */
    protected function generateCachedirFiles()
    {
        $this->consoleWriter->displayText("Generating CACHEDIR.TAG files...", ConsoleWriter::COLOR_YELLOW);

        // Prepare content of the file with the signature
        $fileContent = 'Signature: 8a477f597d28d172789f06886806bc55
# This file is a cache directory tag created by PrestaShop.
# For information about cache directory tags, see:
#	http://www.brynosaurus.com/cachedir/';

        // Specify locations we want to create this file in
        $fileLocations = [
            '/img/tmp/',
            '/var/cache/',
        ];

        foreach ($fileLocations as $fileLocation) {
            $filePath = $this->tempProjectPath . $fileLocation . 'CACHEDIR.TAG';
            if (!file_put_contents($filePath, $fileContent)) {
                throw new BuildException('Unable to create ' . $filePath);
            }
        }
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Install all dependencies.
     *
     * @return $this
     * @throws BuildException
     */
    protected function runComposerInstall()
    {
        $this->consoleWriter->displayText("Running composer install...", ConsoleWriter::COLOR_YELLOW);
        $argProjectPath = escapeshellarg($this->tempProjectPath);
        $autoloaderSuffix = md5($this->version);
        $command = "cd {$argProjectPath} \
            && export SYMFONY_ENV=prod \
            && composer config autoloader-suffix {$autoloaderSuffix} \
            && composer install --no-dev --optimize-autoloader --no-interaction 2>&1";
        exec($command, $output, $returnCode);
        if (!empty($output)) {
            $logPath = __DIR__ . '/../../../var/logs/composer-install.log';
            file_put_contents($logPath, implode(PHP_EOL, $output));
        }

        if ($returnCode !== 0) {
            throw new BuildException('Unable to run composer install.');
        }

        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Build assets.
     *
     * @return $this
     * @throws BuildException
     */
    protected function runBuildAssets()
    {
        $this->consoleWriter->displayText("Running build assets...", ConsoleWriter::COLOR_YELLOW);
        $argProjectPath = escapeshellarg($this->tempProjectPath);
        $command = "cd {$argProjectPath} && make assets 2>&1";
        exec($command, $output, $returnCode);
        if (!empty($output)) {
            $logPath = __DIR__ . '/../../../var/logs/build-assets.log';
            file_put_contents($logPath, implode(PHP_EOL, $output));
        }

        if ($returnCode !== 0) {
            throw new BuildException('Unable to build assets.');
        }

        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Create some required folders and rename a few.
     *
     * @return $this
     * @throws BuildException
     */
    protected function createAndRenameFolders()
    {
        if (!file_exists($this->tempProjectPath . '/var/cache/')) {
            mkdir($this->tempProjectPath . '/var/cache', 0777, true);
        }

        if (!file_exists($this->tempProjectPath . '/var/logs/')) {
            mkdir($this->tempProjectPath . '/var/logs', 0777, true);
        }
        $itemsToRename = ['admin-dev' => 'admin', 'install-dev' => 'install'];
        $basePath = $this->tempProjectPath;

        foreach ($itemsToRename as $oldName => $newName) {
            if (file_exists("$basePath/$oldName")) {
                rename("{$basePath}/$oldName", "{$basePath}/$newName");
            } else {
                throw new BuildException("Unable to rename $oldName to $newName, file does not exist.");
            }
        }

        return $this;
    }

    /**
     * Clean project with unwanted files and folders, generate a checksum xml file,
     * zip the directory and move it to the final destination.
     *
     * @return $this
     */
    protected function createPackage()
    {
        $this->consoleWriter->displayText("Creating package...{$this->lineSeparator}", ConsoleWriter::COLOR_YELLOW);
        $this->cleanTmpProject();
        $this->generateXMLChecksum();
        $this->createZipArchive();
        $this->movePackage();
        $this->consoleWriter->displayText("Package successfully created...{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Create a copy of PrestaShop in a tmp location and
     * remove unwanted files and folders.
     *
     * @return $this
     */
    protected function cleanTmpProject()
    {
        $this->consoleWriter->displayText("--- Cleaning project...", ConsoleWriter::COLOR_YELLOW);
        $this->createAndRenameFolders();
        $this->filesList = $this->getDirectoryStructure($this->tempProjectPath);
        $this->removeUnnecessaryFiles(
            $this->filesList,
            $this->filesRemoveList,
            $this->foldersRemoveList,
            $this->patternsRemoveList,
            $this->tempProjectPath
        );
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Return the directory structure of a given path as an array.
     *
     * @param string $path
     * @return array
     */
    protected function getDirectoryStructure($path)
    {
        $flags = FilesystemIterator::SKIP_DOTS | RecursiveIteratorIterator::CHILD_FIRST;
        $iterator = new RecursiveDirectoryIterator($path, $flags);
        $childrens = iterator_count($iterator);
        $structure = [];

        if ($childrens > 0) {
            $children = $iterator->getChildren();

            for ($index = 0; $index < $childrens; $index += 1) {
                $pathname = $children->getPathname();

                if ($children->hasChildren() === true) {
                    $structure[$pathname] = $this->getDirectoryStructure($pathname);
                } else {
                    $structure[] = $pathname;
                }
                $children->next();
            }
        }
        ksort($structure);

        return $structure;
    }

    /**
     * Delete unwanted files and folders in the PrestaShop tmp directory.
     *
     * @param array $filesList
     * @param array $filesRemoveList
     * @param array $foldersRemoveList
     * @param array $patternsRemoveList
     * @param string $folder
     * @return $this
     * @throws BuildException
     */
    protected function removeUnnecessaryFiles(
        array &$filesList,
        array &$filesRemoveList,
        array &$foldersRemoveList,
        array &$patternsRemoveList,
        $folder
    ) {
        $tmpDir = sys_get_temp_dir();
        $tmpDirPathLength = strlen($tmpDir);

        foreach ($filesList as $key => $value) {
            $pathToTest = $value;

            if (!is_string($pathToTest)) {
                $pathToTest = $key;
            }

            if (substr($pathToTest, 0, $tmpDirPathLength) != $tmpDir) {
                throw new BuildException("Trying to delete a file somewhere else than in $tmpDir, path: $pathToTest");
            }

            if (is_numeric($key)) {
                $argValue = escapeshellarg($value);

                // Remove files.
                foreach ($filesRemoveList as $file_to_remove) {
                    if ($folder.'/'.$file_to_remove == $value) {
                        unset($filesList[$key]);
                        exec("rm -f {$argValue}");

                        continue 2;
                    }
                }

                // Remove folders.
                foreach ($foldersRemoveList as $folder_to_remove) {
                    if ($folder.'/'.$folder_to_remove == $value) {
                        unset($filesList[$key]);
                        exec("rm -rf {$argValue}");

                        continue 2;
                    }
                }

                // Pattern to remove.
                foreach ($patternsRemoveList as $pattern_to_remove) {
                    if (preg_match('#'.$pattern_to_remove.'#', $value) == 1) {
                        unset($filesList[$key]);
                        exec("rm -rf {$argValue}");

                        continue 2;
                    }
                }
            } else {
                $argKey = escapeshellarg($key);

                // Remove folders.
                foreach ($foldersRemoveList as $folder_to_remove) {
                    if ($folder.'/'.$folder_to_remove == $key) {
                        unset($filesList[$key]);
                        exec("rm -rf {$argKey}");

                        continue 2;
                    }
                }

                // Pattern to remove.
                foreach ($patternsRemoveList as $pattern_to_remove) {
                    if (preg_match('#'.$pattern_to_remove.'#', $key) == 1) {
                        unset($filesList[$key]);
                        exec("rm -rf {$argKey}");

                        continue 2;
                    }
                }
                $this->removeUnnecessaryFiles($filesList[$key], $filesRemoveList, $foldersRemoveList, $patternsRemoveList, $folder);
            }
        }

        return $this;
    }

    /**
     * Zip the release if needed.
     *
     * @return $this
     */
    protected function createZipArchive()
    {
        if (!$this->useZip) {
            return $this;
        }
        $this->consoleWriter->displayText("--- Creating zip archive...", ConsoleWriter::COLOR_YELLOW);
        $installerZipFilename = self::INSTALLER_ZIP_FILENAME;
        $argTempProjectPath = escapeshellarg($this->tempProjectPath);
        $argInstallerZipFilename = escapeshellarg($installerZipFilename);
        $argProjectPath = escapeshellarg($this->projectPath);
        $cmd = "cd {$argTempProjectPath} \
            && zip -rq {$argInstallerZipFilename} . \
            && cd -";
        exec($cmd);

        if ($this->useInstaller) {
            exec("cd {$argProjectPath}/tools/build/Library/InstallUnpacker && php compile.php {$this->version} && cd -");
            $zip = new ZipArchive();
            $zip->open("{$this->tempProjectPath}/{$this->zipFileName}", ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile("{$this->tempProjectPath}/{$installerZipFilename}", $installerZipFilename);
            $zip->addFile("{$this->projectPath}/tools/build/Library/InstallUnpacker/index.php", 'index.php');

            // add docs at the root
            $zip->addGlob(
                "{$this->projectPath}/tools/build/doc/*",
                0,
                array('remove_all_path' => true)
            );

            $zip->close();
            exec("rm {$argProjectPath}/tools/build/Library/InstallUnpacker/index.php");
        } else {
            rename(
                "{$this->tempProjectPath}/$installerZipFilename",
                "{$this->tempProjectPath}/{$this->zipFileName}"
            );
        }
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Move the final release to the desired location.
     *
     * @return $this
     */
    protected function movePackage()
    {
        $this->consoleWriter->displayText("--- Move package...", ConsoleWriter::COLOR_YELLOW);
        $tmpDir = sys_get_temp_dir();
        $argTempProjectPath = escapeshellarg($this->tempProjectPath);

        if ($this->useZip) {
            rename(
                "{$this->tempProjectPath}/{$this->zipFileName}",
                "{$this->destinationDir}/prestashop_$this->version.zip"
            );
        } else {
            $argDestinationDir = escapeshellarg($this->destinationDir);
            exec("mv {$argTempProjectPath} {$argDestinationDir}");
        }
        rename(
            "{$tmpDir}/prestashop_$this->version.xml",
            "{$this->destinationDir}/prestashop_$this->version.xml"
        );
        exec("rm -rf {$argTempProjectPath}");
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Create a XML file with the checksum of all the PrestaShop release files.
     *
     * @return $this
     * @throws BuildException
     */
    protected function generateXMLChecksum()
    {
        $this->consoleWriter->displayText("--- Generating XML checksum...", ConsoleWriter::COLOR_YELLOW);
        $tmpDir = sys_get_temp_dir();
        $xmlPath = "{$tmpDir}/prestashop_$this->version.xml";
        $content = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>{$this->lineSeparator}"
            . "<checksum_list>{$this->lineSeparator}"
            . "\t<ps_root_dir version=\"$this->version\">{$this->lineSeparator}"
            . $this->generateXMLDirectoryChecksum($this->filesList)
            . "\t</ps_root_dir>{$this->lineSeparator}"
            . "</checksum_list>{$this->lineSeparator}";

        if (!file_put_contents($xmlPath, $content)) {
            throw new BuildException('Unable to generate XML checksum.');
        }
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Return the checksum of the files and folders given as parameter.
     *
     * @param array $files
     * @return string
     */
    protected function generateXMLDirectoryChecksum(array $files)
    {
        $content = null;
        $subCount = substr_count($this->tempProjectPath, DIRECTORY_SEPARATOR);

        foreach ($files as $key => $value) {
            if (is_numeric($key)) {
                $count = substr_count($value, DIRECTORY_SEPARATOR) - $subCount + 1;
                $file_name = str_replace($this->tempProjectPath, '', $value);
                $file_name = pathinfo($file_name, PATHINFO_BASENAME);

                if (is_link($value)) {
                    $linkTarget = readlink($value);
                    $content .= str_repeat("\t", $count) . "<link name=\"$file_name\">$linkTarget</link>" . PHP_EOL;
                } else {
                    $md5 = md5_file($value);
                    $content .= str_repeat("\t", $count) . "<md5file name=\"$file_name\">$md5</md5file>" . PHP_EOL;
                }
            } else {
                $count = substr_count($key, DIRECTORY_SEPARATOR) - $subCount + 1;
                $dir_name = str_replace($this->tempProjectPath, '', $key);
                $dir_name = pathinfo($dir_name, PATHINFO_BASENAME);
                $content .= str_repeat("\t", $count) . "<dir name=\"$dir_name\">" . PHP_EOL;
                $content .= $this->generateXMLDirectoryChecksum($value);
                $content .= str_repeat("\t", $count) . "</dir>" . PHP_EOL;
            }
        }

        return $content;
    }
}
