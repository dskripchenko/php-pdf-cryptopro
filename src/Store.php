<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

/** Which certificate store of CryptoPro CSP to look in. */
enum Store
{
    /** The store of the user PHP runs as (`CURRENT_USER_STORE`) — the usual place for a server key. */
    case CurrentUser;

    /** The machine store (`LOCAL_MACHINE_STORE`); reading its keys needs the rights to the container. */
    case LocalMachine;
}
