<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser\Message\Part;

use Psr\Http\Message\StreamInterface;
use ZBateson\MailMimeParser\Header\HeaderFactory;
use ZBateson\MailMimeParser\Message\PartFilterFactory;

/**
 * Represents a single part of a multi-part mime message.
 *
 * A MimePart object may have any number of child parts, or may be a child
 * itself with its own parent or parents.
 *
 * The content of the part can be read from its PartStream resource handle,
 * accessible via MessagePart::getContentResourceHandle.
 *
 * @author Zaahid Bateson
 */
class MimePart extends MessagePart
{
    use MimePartHeaderTrait {
        MimePartHeaderTrait::__construct as private mimePartHeaderConstructor;
    }
    use MimePartChildrenTrait {
        MimePartChildrenTrait::__construct as private mimePartChildrenConstructor;
    }

    /**
     * Sets up class dependencies.
     *
     * @param HeaderFactory $headerFactory 
     * @param PartFilterFactory $partFilterFactory
     * @param PartBuilder $partBuilder
     * @param PartStreamFilterManager $partStreamFilterManager
     * @param StreamInterface $stream
     * @param StreamInterface $contentStream
     */
    public function __construct(
        HeaderFactory $headerFactory,
        PartFilterFactory $partFilterFactory,
        PartBuilder $partBuilder,
        PartStreamFilterManager $partStreamFilterManager,
        StreamInterface $stream,
        StreamInterface $contentStream = null
    ) {
        parent::__construct($partStreamFilterManager, $stream, $contentStream);
        $this->mimePartChildrenConstructor($partFilterFactory, $partBuilder, $stream);
        $this->mimePartHeaderConstructor($headerFactory, $partBuilder);
    }

    /**
     * Returns true if this part's mime type is multipart/*
     *
     * @return bool
     */
    public function isMultiPart()
    {
        // casting to bool, preg_match returns 1 for true
        return (bool) (preg_match(
            '~multipart/\w+~i',
            $this->getContentType()
        ));
    }
    
    /**
     * Returns a filename for the part if one is defined, or null otherwise.
     * 
     * @return string
     */
    public function getFilename()
    {
        return $this->getHeaderParameter(
            'Content-Disposition',
            'filename',
            $this->getHeaderParameter(
                'Content-Type',
                'name'
            )
        );
    }
    
    /**
     * Returns true.
     * 
     * @return bool
     */
    public function isMime()
    {
        return true;
    }
    
    /**
     * Returns true if this part's mime type is text/plain, text/html or if the
     * Content-Type header defines a charset.
     * 
     * @return bool
     */
    public function isTextPart()
    {
        return ($this->getCharset() !== null);
    }
    
    /**
     * Returns the lower-cased, trimmed value of the Content-Type header.
     * 
     * Parses the Content-Type header, defaults to returning text/plain if not
     * defined.
     * 
     * @return string
     */
    public function getContentType($default = 'text/plain')
    {
        return trim(strtolower($this->getHeaderValue('Content-Type', $default)));
    }
    
    /**
     * Returns the upper-cased charset of the Content-Type header's charset
     * parameter if set, US-ASCII if the Content-Type is text/plain or text/html
     * and the charset parameter isn't set, or null otherwise.
     * 
     * @return string
     */
    public function getCharset()
    {
        $charset = $this->getHeaderParameter('Content-Type', 'charset');
        if ($charset === null) {
            $contentType = $this->getContentType();
            if ($contentType === 'text/plain' || $contentType === 'text/html') {
                return 'US-ASCII';
            }
            return null;
        }
        return trim(strtoupper($charset));
    }
    
    /**
     * Returns the content's disposition, defaulting to 'inline' if not set.
     * 
     * @return string
     */
    public function getContentDisposition($default = 'inline')
    {
        return strtolower($this->getHeaderValue('Content-Disposition', $default));
    }
    
    /**
     * Returns the content-transfer-encoding used for this part, defaulting to
     * '7bit' if not set.
     * 
     * @return string
     */
    public function getContentTransferEncoding($default = '7bit')
    {
        static $translated = [
            'x-uue' => 'x-uuencode',
            'uue' => 'x-uuencode',
            'uuencode' => 'x-uuencode'
        ];
        $type = strtolower($this->getHeaderValue('Content-Transfer-Encoding', $default));
        if (isset($translated[$type])) {
            return $translated[$type];
        }
        return $type;
    }
}
