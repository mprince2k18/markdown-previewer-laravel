<?php
/**
 * File containing the class {@see \Mprince\MarkdownViewer\DocsViewer}.
 *
 * @package MarkdownViewer
 * @see \Mprince\MarkdownViewer\DocsViewer
 */

declare(strict_types=1);

namespace Mprince\MarkdownViewer;

use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\File;
use AppUtils\OutputBuffering;use AppUtils\OutputBuffering_Exception;

/**
 * Renders the documentation viewer UI, using the
 * list of documents contained in the manager instance.
 *
 * @package MarkdownViewer
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
class DocsViewer
{
    public const ERROR_NO_DOCUMENTS_AVAILABLE = 82001;

    private string $title = 'Documentation';
    private string $menuLabel = 'Available documents';
    private DocsManager $docs;
    private bool $darkMode = false;
    private string $vendorURL;
    private string $packageURL;

    /**
     * @param DocsManager $manager
     * @param string $vendorURL
     * @throws DocsException
     * @see DocsViewer::ERROR_NO_DOCUMENTS_AVAILABLE
     */
    public function __construct(DocsManager $manager, string $vendorURL)
    {
        $this->docs = $manager;
        $this->vendorURL = rtrim($vendorURL, '/');

        if(!$this->docs->hasFiles()) {
            throw new DocsException(
                'Cannot start viewer, the are no documents to display.',
                '',
                self::ERROR_NO_DOCUMENTS_AVAILABLE
            );
        }
    }

    public function makeDarkMode() : DocsViewer
    {
        $this->darkMode = true;
        return $this;
    }

    /**
     * Sets the title of the document and the navigation label.
     *
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title) : DocsViewer
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the label of the menu item listing all the available documents.
     *
     * @param string $label
     * @return $this
     */
    public function setMenuLabel(string $label) : DocsViewer
    {
        $this->menuLabel = $label;
        return $this;
    }

    public function getActiveFileID() : string
    {
        if(isset($_REQUEST['doc']) && $this->docs->idExists($_REQUEST['doc'])) {
            return $_REQUEST['doc'];
        }

        return $this->docs->getFirstFile()->getID();
    }

    public function getActiveFile() : DocFile
    {
        return $this->docs->getByID($this->getActiveFileID());
    }

    public function display() : void
    {
        $parser = new DocParser($this->getActiveFile());

?><!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $this->title ?></title>
    </head>
    <body>
        <nav class="navbar navbar-dark bg-dark fixed-top">
            <a class="navbar-brand" href="#"><?php echo $this->title ?></a>
            <ul class="navbar-nav mr-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <?php echo $this->menuLabel ?>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown" style="position: absolute">
                        <?php
                        $files = $this->docs->getFiles();

                        foreach ($files as $file) {
                            ?>
                            <a class="dropdown-item" href="?doc=<?php echo $file->getID() ?>">
                                <?php echo $file->getTitle() ?>
                            </a>
                            <?php
                        }
                        ?>
                    </div>
                </li>
            </ul>
        </nav>
        <table id="scaffold">
            <tbody>
                <tr>
                    <td id="sidebar">
                        <div class="sidebar-wrapper">
                            <?php echo Markdown::parse(File::get(base_path('teleman/_sidebar.md'))); ?>
                            <hr>
                            <?php echo $this->renderMenu($parser->getHeaders()); ?>
                        </div>
                    </td>
                    <td id="content">
                        <div class="content-wrapper">
                            <?php echo $parser->render(); ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
            if($this->darkMode) {
                ?>
                    <link rel="stylesheet" href="<?php echo $this->getPackageURL() ?>/css/slate.min.css">
                <?php
            }
            else
            {
                ?>
                    <link rel="stylesheet" href="<?php echo $this->vendorURL ?>/twbs/bootstrap/dist/css/bootstrap.min.css">
                <?php
            }
        ?>
        <link rel="stylesheet" href="<?php echo $this->getPackageURL() ?>/css/styles.css">
        <?php

            if($this->darkMode) {
                ?>
                    <link rel="stylesheet" href="<?php echo $this->getPackageURL() ?>/css/styles-dark.css">
                <?php
            }

        ?>
        <script src="<?php echo $this->vendorURL ?>/components/jquery/jquery.js"></script>
        <script src="<?php echo $this->vendorURL ?>/twbs/bootstrap/dist/js/bootstrap.js"></script>
        <script>
            const links = document.querySelectorAll('a[href]');
            links.forEach(link => {
                link.href = link.href.replace(/\.md$/, '');
            });
        </script>
    </body>
</html><?php
    }

    public function setPackageURL(string $url) : DocsViewer
    {
        $this->packageURL = rtrim($url, '/');
        return $this;
    }

    private function getPackageURL() : string
    {
        if(!empty($this->packageURL)) {
            return $this->packageURL;
        }

        return $this->vendorURL.'/mistralys/markdown-viewer';
    }

    /**
     * @param DocHeader[] $headers
     * @return string
     * @throws OutputBuffering_Exception
     */
    private function renderMenu(array $headers) : string
    {
        OutputBuffering::start();

        ?>
        <ul class="nav-level-0">
            <?php
            foreach ($headers as $header)
            {
                echo $header->render();
            }
            ?>
        </ul>
        <?php

        return OutputBuffering::get();
    }
}
