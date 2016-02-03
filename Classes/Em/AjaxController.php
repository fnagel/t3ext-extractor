<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Extractor\Em;

use Causal\Extractor\Service;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * AJAX controller for Extension Manager.
 *
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
class AjaxController
{

    /**
     * Renders the menu so that it can be returned as response to an AJAX call
     *
     * @param array $params Array of parameters from the AJAX interface, currently unused
     * @param \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj Object of type AjaxRequestHandler
     * @return void
     */
    public function renderAjax(array $params = [], \TYPO3\CMS\Core\Http\AjaxRequestHandler &$ajaxObj = NULL) {
        $ajaxObj->setContentFormat('json');
        $success = false;
        $html = '';
        $preview = '';

        if ($GLOBALS['BE_USER']->isAdmin()) {
            if (version_compare(TYPO3_version, '7.0', '>=')) {
                /** @var \TYPO3\CMS\Core\Http\ServerRequest $request */
                $request = $params['request'];
                $queryParameters = $request->getQueryParams();
                $file = $queryParameters['file'];
                $service = $queryParameters['service'];
            } else {
                $file = GeneralUtility::_GET('file');
                $service = GeneralUtility::_GET('service');
            }

            $publicUrl = '';
            $file = $this->getFile($file, $publicUrl);

            /** @var Service\ServiceInterface $extractor */
            $extractor = null;

            try {
                switch ($service) {
                    case 'exiftool':
                        $extractor = GeneralUtility::makeInstance(Service\ExifTool\ExifToolService::class);
                        break;
                    case 'pdfinfo':
                        $extractor = GeneralUtility::makeInstance(Service\Pdfinfo\PdfInfoService::class);
                        break;
                    case 'php':
                        $extractor = GeneralUtility::makeInstance(Service\Php\PhpService::class);
                        break;
                    case 'tika':
                        $extractor = Service\Tika\TikaServiceFactory::getTika();
                        break;
                }
            } catch (\Exception $e) {
                $html = $e->getMessage();
            }

            if ($extractor !== null) {
                $success = true;
                $metadata = $extractor->extractMetadata($file);
                $html = $this->htmlizeMetadata($metadata);

                if (in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $preview = '<img src="' . $publicUrl . '" alt="" width="300" />';
                }
            }
        }

        $ajaxObj->setContent([
            'success' => $success,
            'preview' => $preview,
            'html' => $html,
        ]);
    }

    /**
     * Returns a FAL file.
     *
     * @param string $reference
     * @param string &$publicUrl
     * @return \TYPO3\CMS\Core\Resource\File
     */
    protected function getFile($reference, &$publicUrl)
    {
        $file = null;
        $extensionPrefix = 'EXT:extractor/Resources/Public/';

        /** @var \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class);

        if (GeneralUtility::isFirstPartOfStr($reference, $extensionPrefix)) {
            $fileName = substr($reference, strlen($extensionPrefix));
            $recordData = [
                'uid' => 0,
                'pid' => 0,
                'name' => 'Resource Extension Storage',
                'description' => 'Internal storage, mounting the extension Resources/Public directory.',
                'driver' => 'Local',
                'processingfolder' => '',
                // legacy code
                'configuration' => '',
                'is_online' => true,
                'is_browsable' => false,
                'is_public' => false,
                'is_writable' => false,
                'is_default' => false,
            ];
            $storageConfiguration = [
                'basePath' => GeneralUtility::getFileAbsFileName($extensionPrefix),
                'pathType' => 'absolute'
            ];

            $virtualStorage = $resourceFactory->createStorageObject($recordData, $storageConfiguration);
            $name = PathUtility::basename($fileName);
            $extension = strtolower(substr($name, strrpos($name, '.') + 1));

            /** @var \TYPO3\CMS\Core\Resource\File $file */
            $file = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Resource\File::class,
                [
                    'identifier' => '/' . $fileName,
                    'name' => $name,
                    'extension' => $extension,
                ],
                $virtualStorage,
                [
                    // Trick to let FAL thinks the file is indexed
                    '_' => 1,
                ]
            );

            $publicUrl = PathUtility::getAbsoluteWebPath(rtrim($storageConfiguration['basePath'], '/') . $file->getIdentifier());
        } elseif (preg_match('/^file:(\d+):(.*)$/', $reference, $matches)) {
            $storage = $resourceFactory->getStorageObject((int)$matches[1]);
            $file = $storage->getFile($matches[2]);
            $publicUrl = $file->getPublicUrl(true);
        }

        return $file;
    }

    /**
     * HTML-izes an array of metadata.
     *
     * @param array $metadata
     * @param int $indent
     * @param null|string $parent
     * @return mixed
     */
    protected function htmlizeMetadata(array $metadata, $indent = 0, $parent = null)
    {
        $html = [];

        $html[] = 'array(';

        foreach ($metadata as $key => $value) {
            $keyName = ($parent ? $parent . '|' : '') . $key;
            $postProcessor = $this->suggestPostProcessor($value, $key);
            $propertyPath = $keyName . ($postProcessor ? '->' . $postProcessor : '');

            $property = '\'<a class="tx-extractor-property" href="#" data-property="' . htmlspecialchars($propertyPath) . '">' . htmlspecialchars($key) . '</a>\'';

            if (is_array($value)) {
                $value = $this->htmlizeMetadata($value, $indent + 1, $keyName);
            } else {
                $value = '\'' . htmlspecialchars(str_replace('\'', '\\\'', $value)) . '\'';
            }

            $html[] = str_repeat('  ', $indent + 1) . $property . ' => ' . $value . ',';
        }

        $html[] = str_repeat('  ', $indent) . ')';

        return implode(LF, $html);
    }

    /**
     * Suggests a post-processor to be used for extracting a given value.
     *
     * @param mixed $value
     * @param string $property
     * @return string
     */
    protected function suggestPostProcessor($value, $property)
    {
        $postProcessor = null;

        switch (true) {
            case stripos($property, 'date') !== false:
            case stripos($property, 'modified') !== false:
            case stripos($property, 'created') !== false:
                $postProcessor = 'Causal\\Extractor\\Utility\\DateTime::timestamp';
                break;
            case stripos($property, 'gps') !== false:
                $postProcessor = 'Causal\\Extractor\\Utility\\Gps::toDecimal()';
                break;
        }

        return $postProcessor;
    }

}