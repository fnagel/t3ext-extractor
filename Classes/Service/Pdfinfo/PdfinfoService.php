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

namespace Causal\Extractor\Service\Pdfinfo;

use Causal\Extractor\Service\AbstractService;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A Pdfinfo service implementation.
 *
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
class PdfinfoService extends AbstractService
{

    /**
     * PdfinfoService constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $pdfInfo = GeneralUtility::getFileAbsFileName($this->settings['tools_pdfinfo'], false);
        if (!is_file($pdfInfo)) {
            throw new \RuntimeException(
                'Invalid path or filename for Pdfinfo: ' . $this->settings['tools_pdfinfo'],
                1445271483
            );
        }
    }

    /**
     * Returns a list of supported file types.
     *
     * @return array
     */
    public function getSupportedFileTypes()
    {
        return array('pdf');
    }

    /**
     * Takes a file reference and extracts its metadata.
     *
     * @param string $fileName Path to the file
     * @return array
     */
    public function extractMetadataFromLocalFile($fileName)
    {
        $pdfinfoCommand = GeneralUtility::getFileAbsFileName($this->settings['tools_pdfinfo'], false)
            . ' ' . escapeshellarg($fileName);

        $shellOutput = array();
        CommandUtility::exec($pdfinfoCommand, $shellOutput);
        $metadata = array();
        foreach ($shellOutput as $line) {
            list($key, $value) = GeneralUtility::trimExplode(':', $line, true, 2);
            $metadata[$key] = $value;
        }

        return $metadata;
    }

}