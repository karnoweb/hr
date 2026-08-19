<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when a document mutation is attempted while the document is not
 * in an editable status (only Draft may be edited).
 */
class DocumentLockedException extends HrException {}
