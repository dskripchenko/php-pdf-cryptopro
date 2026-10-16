<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

/** What CryptoPro CSP is given to sign. */
enum SignMode
{
    /**
     * The document's hash (`CPSignedData::SignHash`): only the digest crosses
     * into the CSP, whatever the size of the document.
     */
    case Hash;

    /**
     * The document's bytes (`CPSignedData::SignCades`, detached): for
     * versions of the extension without `SignHash`.
     */
    case Content;
}
