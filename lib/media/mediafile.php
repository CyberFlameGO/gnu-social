<?php
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Abstraction for media files
 *
 * @category  Media
 * @package   GNUsocial
 *
 * @author    Robin Millette <robin@millette.info>
 * @author    Miguel Dantas <biodantas@gmail.com>
 * @author    Zach Copley <zach@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2008-2009, 2019-2020 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 */
defined('GNUSOCIAL') || die();

/**
 * Class responsible for abstracting media files
 */
class MediaFile
{
    public $id;
    public $filepath;
    public $filename;
    public $fileRecord;
    public $fileurl;
    public $short_fileurl;
    public $mimetype;

    /**
     * MediaFile constructor.
     *
     * @param string $filepath The path of the file this media refers to. Required
     * @param string $mimetype The mimetype of the file. Required
     * @param string|null $filehash The hash of the file, if known. Optional
     * @param int|null $id The DB id of the file. Int if known, null if not.
     *                     If null, it searches for it. If -1, it skips all DB
     *                     interactions (useful for temporary objects)
     *
     * @throws ClientException
     * @throws NoResultException
     * @throws ServerException
     */
    public function __construct(string $filepath, string $mimetype, ?string $filehash = null, ?int $id = null)
    {
        $this->filepath = $filepath;
        $this->filename = basename($this->filepath);
        $this->mimetype = $mimetype;
        $this->filehash = self::getHashOfFile($this->filepath, $filehash);
        $this->id = $id;

        // If id is -1, it means we're dealing with a temporary object and don't want to store it in the DB,
        // or add redirects
        if ($this->id !== -1) {
            if (!empty($this->id)) {
                // If we have an id, load it
                $this->fileRecord = new File();
                $this->fileRecord->id = $this->id;
                if (!$this->fileRecord->find(true)) {
                    // If we have set an ID, we need that ID to exist!
                    throw new NoResultException($this->fileRecord);
                }
            } else {
                // Otherwise, store it
                $this->fileRecord = $this->storeFile();
            }

            $this->fileurl = common_local_url(
                'attachment',
                ['attachment' => $this->fileRecord->id]
            );

            $this->short_fileurl = common_shorten_url($this->fileurl);
        }
    }

    public function attachToNotice(Notice $notice)
    {
        File_to_post::processNew($this->fileRecord, $notice);
    }

    public function getPath()
    {
        return File::path($this->filename);
    }

    public function shortUrl()
    {
        return $this->short_fileurl;
    }

    public function getEnclosure()
    {
        return $this->getFile()->getEnclosure();
    }

    public function delete()
    {
        @unlink($this->filepath);
    }

    public function getFile()
    {
        if (!$this->fileRecord instanceof File) {
            throw new ServerException('File record did not exist for MediaFile');
        }

        return $this->fileRecord;
    }

    /**
     * Calculate the hash of a file.
     *
     * This won't work for files >2GiB because PHP uses only 32bit.
     *
     * @param string $filepath
     * @param null|string $filehash
     *
     * @return string
     * @throws ServerException
     *
     */
    public static function getHashOfFile(string $filepath, $filehash = null)
    {
        assert(!empty($filepath), __METHOD__ . ': filepath cannot be null');
        if ($filehash === null) {
            // Calculate if we have an older upload method somewhere (Qvitter) that
            // doesn't do this before calling new MediaFile on its local files...
            $filehash = hash_file(File::FILEHASH_ALG, $filepath);
            if ($filehash === false) {
                throw new ServerException('Could not read file for hashing');
            }
        }
        return $filehash;
    }

    /**
     * Retrieve or insert as a file in the DB
     *
     * @return object File
     * @throws ServerException
     *
     * @throws ClientException
     */
    protected function storeFile()
    {
        try {
            $file = File::getByHash($this->filehash);
            // We're done here. Yes. Already. We assume sha256 won't collide on us anytime soon.
            return $file;
        } catch (NoResultException $e) {
            // Well, let's just continue below.
        }

        $fileurl = common_local_url('attachment_view', ['filehash' => $this->filehash]);

        $file = new File;

        $file->filename = $this->filename;
        $file->urlhash = File::hashurl($fileurl);
        $file->url = $fileurl;
        $file->filehash = $this->filehash;
        $file->size = filesize($this->filepath);
        if ($file->size === false) {
            throw new ServerException('Could not read file to get its size');
        }
        $file->date = time();
        $file->mimetype = $this->mimetype;

        $file_id = $file->insert();

        if ($file_id === false) {
            common_log_db_error($file, 'INSERT', __FILE__);
            // TRANS: Client exception thrown when a database error was thrown during a file upload operation.
            throw new ClientException(_m('There was a database error while saving your file. Please try again.'));
        }

        // Set file geometrical properties if available
        try {
            $image = ImageFile::fromFileObject($file);
            $orig = clone $file;
            $file->width = $image->width;
            $file->height = $image->height;
            $file->update($orig);

            // We have to cleanup after ImageFile, since it
            // may have generated a temporary file from a
            // video support plugin or something.
            // FIXME: Do this more automagically.
            if ($image->getPath() != $file->getPath()) {
                $image->unlink();
            }
        } catch (ServerException $e) {
            // We just couldn't make out an image from the file. This
            // does not have to be UnsupportedMediaException, as we can
            // also get ServerException from files not existing etc.
        }

        return $file;
    }

    /**
     * The maximum allowed file size, as a string
     */
    public static function maxFileSize()
    {
        $value = self::maxFileSizeInt();
        if ($value > 1024 * 1024) {
            $value = $value / (1024 * 1024);
            // TRANS: Number of megabytes. %d is the number.
            return sprintf(_m('%dMB', '%dMB', $value), $value);
        } elseif ($value > 1024) {
            $value = $value / 1024;
            // TRANS: Number of kilobytes. %d is the number.
            return sprintf(_m('%dkB', '%dkB', $value), $value);
        } else {
            // TRANS: Number of bytes. %d is the number.
            return sprintf(_m('%dB', '%dB', $value), $value);
        }
    }

    /**
     * The maximum allowed file size, as an int
     */
    public static function maxFileSizeInt(): int
    {
        return common_config('attachments', 'file_quota');
    }

    /**
     * Encodes a file name and a file hash in the new file format, which is used to avoid
     * having an extension in the file, removing trust in extensions, while keeping the original name
     *
     * @param null|string $original_name
     * @param string $filehash
     * @param null|string|bool $ext from File::getSafeExtension
     *
     * @return string
     * @throws ClientException
     * @throws ServerException
     */
    public static function encodeFilename($original_name, string $filehash, $ext = null): string
    {
        if (empty($original_name)) {
            $original_name = _m('Untitled attachment');
        }

        // If we're given an extension explicitly, use it, otherwise...
        $ext = $ext ?:
            // get a replacement extension if configured, returns false if it's blocked,
            // null if no extension
            File::getSafeExtension($original_name);
        if ($ext === false) {
            throw new ClientException(_m('Blacklisted file extension.'));
        }

        if (!empty($ext)) {
            // Remove dots if we have them (make sure they're not repeated)
            $ext = preg_replace('/^\.+/', '', $ext);
            $original_name = preg_replace('/\.+.+$/i', ".{$ext}", $original_name);
        }

        $enc_name = bin2hex($original_name);
        return "{$enc_name}-{$filehash}";
    }

    /**
     * Decode the new filename format
     *
     * @return false | null | string on failure, no match (old format) or original file name, respectively
     */
    public static function decodeFilename(string $encoded_filename)
    {
        // Should match:
        //   hex-hash
        //   thumb-id-widthxheight-hex-hash
        // And return the `hex` part
        $ret = preg_match('/^(.*-)?([^-]+)-[^-]+$/', $encoded_filename, $matches);
        if ($ret === false) {
            return false;
        } elseif ($ret === 0 || !ctype_xdigit($matches[2])) {
            return null; // No match
        } else {
            $filename = hex2bin($matches[2]);

            // Matches extension
            if (preg_match('/^(.+?)\.(.+)$/', $filename, $sub_matches) === 1) {
                $ext = $sub_matches[2];
                // Previously, there was a blacklisted extension array, which could have an alternative
                // extension, such as phps, to replace php. We want to turn it back (this is deprecated,
                // as it no longer makes sense, since we don't trust trust files based on extension,
                // but keep the feature)
                $blacklist = common_config('attachments', 'extblacklist');
                if (is_array($blacklist)) {
                    foreach ($blacklist as $upload_ext => $safe_ext) {
                        if ($ext === $safe_ext) {
                            $ext = $upload_ext;
                            break;
                        }
                    }
                }
                return "{$sub_matches[1]}.{$ext}";
            } else {
                // No extension, don't bother trying to replace it
                return $filename;
            }
        }
    }

    /**
     * Create a new MediaFile or ImageFile object from an upload
     *
     * Tries to set the mimetype correctly, using the most secure method available and rejects the file otherwise.
     * In case the upload is an image, this function returns an new ImageFile (which extends MediaFile)
     * The filename has a new format:
     *   bin2hex("{$original_name}.{$ext}")."-{$filehash}"
     * This format should be respected. Notice the dash, which is important to distinguish it from the previous
     * format ("{$hash}.{$ext}")
     *
     * @param string $param Form name
     * @param Profile|null $scoped
     * @return ImageFile|MediaFile
     * @throws ClientException
     * @throws InvalidFilenameException
     * @throws NoResultException
     * @throws NoUploadedMediaException
     * @throws ServerException
     * @throws UnsupportedMediaException
     * @throws UseFileAsThumbnailException
     */
    public static function fromUpload(string $param = 'media', ?Profile $scoped = null)
    {
        // The existence of the "error" element means PHP has processed it properly even if it was ok.
        if (!(isset($_FILES[$param], $_FILES[$param]['error']))) {
            throw new NoUploadedMediaException($param);
        }

        switch ($_FILES[$param]['error']) {
            case UPLOAD_ERR_OK: // success, jump out
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                // TRANS: Exception thrown when too large a file is uploaded.
                // TRANS: %s is the maximum file size, for example "500b", "10kB" or "2MB".
                throw new ClientException(sprintf(
                    _m('That file is too big. The maximum file size is %s.'),
                    self::maxFileSize()
                ));
            case UPLOAD_ERR_PARTIAL:
                @unlink($_FILES[$param]['tmp_name']);
                // TRANS: Client exception.
                throw new ClientException(_m('The uploaded file was only partially uploaded.'));
            case UPLOAD_ERR_NO_FILE:
                // No file; probably just a non-AJAX submission.
                throw new NoUploadedMediaException($param);
            case UPLOAD_ERR_NO_TMP_DIR:
                // TRANS: Client exception thrown when a temporary folder is not present to store a file upload.
                throw new ClientException(_m('Missing a temporary folder.'));
            case UPLOAD_ERR_CANT_WRITE:
                // TRANS: Client exception thrown when writing to disk is not possible during a file upload operation.
                throw new ClientException(_m('Failed to write file to disk.'));
            case UPLOAD_ERR_EXTENSION:
                // TRANS: Client exception thrown when a file upload operation has been stopped by an extension.
                throw new ClientException(_m('File upload stopped by extension.'));
            default:
                common_log(LOG_ERR, __METHOD__ . ': Unknown upload error ' . $_FILES[$param]['error']);
                // TRANS: Client exception thrown when a file upload operation has failed with an unknown reason.
                throw new ClientException(_m('System error uploading file.'));
        }

        $filehash = strtolower(self::getHashOfFile($_FILES[$param]['tmp_name']));

        try {
            $file = File::getByHash($filehash);
            // If no exception is thrown the file exists locally, so we'll use that and just add redirections.
            // but if the _actual_ locally stored file doesn't exist, getPath will throw FileNotFoundException
            $filepath = $file->getPath();
            $mimetype = $file->mimetype;
        } catch (FileNotFoundException | NoResultException $e) {
            // We have to save the upload as a new local file. This is the normal course of action.
            if ($scoped instanceof Profile) {
                // Throws exception if additional size does not respect quota
                // This test is only needed, of course, if we're uploading something new.
                File::respectsQuota($scoped, $_FILES[$param]['size']);
            }

            $mimetype = self::getUploadedMimeType($_FILES[$param]['tmp_name'], $_FILES[$param]['name']);
            $media = common_get_mime_media($mimetype);

            $basename = basename($_FILES[$param]['name']);

            if ($media == 'image') {
                // Use -1 for the id to avoid adding this temporary file to the DB
                $img = new ImageFile(-1, $_FILES[$param]['tmp_name']);
                // Validate the image by re-encoding it. Additionally normalizes old formats to WebP,
                // keeping GIF untouched if animated
                $outpath = $img->resizeTo($img->filepath);
                $ext = image_type_to_extension($img->preferredType(), false);
            }
            $filename = self::encodeFilename($basename, $filehash, isset($ext) ? $ext : File::getSafeExtension($basename));

            $filepath = File::path($filename);

            if ($media == 'image') {
                $result = rename($outpath, $filepath);
            } else {
                $result = move_uploaded_file($_FILES[$param]['tmp_name'], $filepath);
            }
            if (!$result) {
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                // UX: too specific
                throw new ClientException(_m('File could not be moved to destination directory.'));
            }

            if ($media == 'image') {
                return new ImageFile(null, $filepath);
            }
        }
        return new self($filepath, $mimetype, $filehash);
    }

    /**
     * Create a new MediaFile or ImageFile object from an url
     *
     * Tries to set the mimetype correctly, using the most secure method available and rejects the file otherwise.
     * In case the url is an image, this function returns an new ImageFile (which extends MediaFile)
     * The filename has the following format: bin2hex("{$original_name}.{$ext}")."-{$filehash}"
     *
     * @param string $url Remote media URL
     * @param Profile|null $scoped
     * @param string|null $name
     * @return ImageFile|MediaFile
     * @throws ClientException
     * @throws FileNotFoundException
     * @throws InvalidFilenameException
     * @throws NoResultException
     * @throws ServerException
     * @throws UnsupportedMediaException
     * @throws UseFileAsThumbnailException
     */
    public static function fromUrl(string $url, ?Profile $scoped = null, ?string $name = null)
    {
        if (!common_valid_http_url($url)) {
            // TRANS: Server exception. %s is a URL.
            throw new ServerException(sprintf('Invalid remote media URL %s.', $url));
        }

        $temp_filename = tempnam(sys_get_temp_dir(), 'tmp' . common_timestamp());

        try {
            $fileData = HTTPClient::quickGet($url);
            file_put_contents($temp_filename, $fileData);
            unset($fileData);    // No need to carry this in memory.

            $filehash = strtolower(self::getHashOfFile($temp_filename));

            try {
                $file = File::getByHash($filehash);
                // If no exception is thrown the file exists locally, so we'll use that and just add redirections.
                // but if the _actual_ locally stored file doesn't exist, getPath will throw FileNotFoundException
                $filepath = $file->getPath();
                $mimetype = $file->mimetype;
            } catch (FileNotFoundException | NoResultException $e) {
                // We have to save the downloaded as a new local file. This is the normal course of action.
                if ($scoped instanceof Profile) {
                    // Throws exception if additional size does not respect quota
                    // This test is only needed, of course, if we're uploading something new.
                    File::respectsQuota($scoped, filesize($temp_filename));
                }

                $mimetype = self::getUploadedMimeType($temp_filename, $name ?? false);
                $media = common_get_mime_media($mimetype);

                $basename = basename($name ?? $temp_filename);

                if ($media == 'image') {
                    // Use -1 for the id to avoid adding this temporary file to the DB
                    $img = new ImageFile(-1, $temp_filename);
                    // Validate the image by re-encoding it. Additionally normalizes old formats to PNG,
                    // keeping JPEG and GIF untouched
                    $outpath = $img->resizeTo($img->filepath);
                    $ext = image_type_to_extension($img->preferredType(), false);
                }
                $filename = self::encodeFilename($basename, $filehash, isset($ext) ? $ext : File::getSafeExtension($basename));

                $filepath = File::path($filename);

                if ($media == 'image') {
                    $result = rename($outpath, $filepath);
                } else {
                    $result = rename($temp_filename, $filepath);
                }
                if (!$result) {
                    // TRANS: Client exception thrown when a file upload operation fails because the file could
                    // TRANS: not be moved from the temporary folder to the permanent file location.
                    throw new ServerException(_m('File could not be moved to destination directory.'));
                }

                if ($media == 'image') {
                    return new ImageFile(null, $filepath);
                }
            }
            return new self($filepath, $mimetype, $filehash);
        } catch (Exception $e) {
            unlink($temp_filename); // Garbage collect
            throw $e;
        }
    }

    public static function fromFilehandle($fh, Profile $scoped = null)
    {
        $stream = stream_get_meta_data($fh);
        // So far we're only handling filehandles originating from tmpfile(),
        // so we can always do hash_file on $stream['uri'] as far as I can tell!
        $filehash = hash_file(File::FILEHASH_ALG, $stream['uri']);

        try {
            $file = File::getByHash($filehash);
            // Already have it, so let's reuse the locally stored File
            // by using getPath we also check whether the file exists
            // and throw a FileNotFoundException with the path if it doesn't.
            $filename = basename($file->getPath());
            $mimetype = $file->mimetype;
        } catch (FileNotFoundException $e) {
            // This happens if the file we have uploaded has disappeared
            // from the local filesystem for some reason. Since we got the
            // File object from a sha256 check in fromFilehandle, it's safe
            // to just copy the uploaded data to disk!

            fseek($fh, 0);  // just to be sure, go to the beginning
            // dump the contents of our filehandle to the path from our exception
            // and report error if it failed.
            if (false === file_put_contents($e->path, fread($fh, filesize($stream['uri'])))) {
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                throw new ClientException(_m('File could not be moved to destination directory.'));
            }
            if (!chmod($e->path, 0664)) {
                common_log(LOG_ERR, 'Could not chmod uploaded file: ' . _ve($e->path));
            }

            $filename = basename($file->getPath());
            $mimetype = $file->mimetype;
        } catch (NoResultException $e) {
            if ($scoped instanceof Profile) {
                File::respectsQuota($scoped, filesize($stream['uri']));
            }

            $mimetype = self::getUploadedMimeType($stream['uri']);

            $filename = strtolower($filehash) . '.' . File::guessMimeExtension($mimetype);
            $filepath = File::path($filename);

            $result = copy($stream['uri'], $filepath) && chmod($filepath, 0664);

            if (!$result) {
                common_log(LOG_ERR, 'File could not be moved (or chmodded) from ' . _ve($stream['uri']) . ' to ' . _ve($filepath));
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                throw new ClientException(_m('File could not be moved to destination directory.'));
            }
        }

        return new self($filename, $mimetype, $filehash);
    }

    /**
     * Attempt to identify the content type of a given file.
     *
     * @param string $filepath filesystem path as string (file must exist)
     * @param bool $originalFilename (optional) for extension-based detection
     *
     * @return string
     *
     * @fixme this seems to tie a front-end error message in, kinda confusing
     *
     * @throws ServerException
     *
     * @throws ClientException if type is known, but not supported for local uploads
     */
    public static function getUploadedMimeType(string $filepath, $originalFilename = false)
    {
        // We only accept filenames to existing files

        $mimetype = null;

        // From CodeIgniter
        // We'll need this to validate the MIME info string (e.g. text/plain; charset=us-ascii)
        $regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+)(;\s[^\/]+)?$/';
        /**
         * Fileinfo extension - most reliable method
         *
         * Apparently XAMPP, CentOS, cPanel and who knows what
         * other PHP distribution channels EXPLICITLY DISABLE
         * ext/fileinfo, which is otherwise enabled by default
         * since PHP 5.3 ...
         */
        if (function_exists('finfo_file')) {
            $finfo = @finfo_open(FILEINFO_MIME);
            // It is possible that a FALSE value is returned, if there is no magic MIME database
            // file found on the system
            if (is_resource($finfo)) {
                $mime = @finfo_file($finfo, $filepath);
                finfo_close($finfo);
                /* According to the comments section of the PHP manual page,
                 * it is possible that this function returns an empty string
                 * for some files (e.g. if they don't exist in the magic MIME database)
                 */
                if (is_string($mime) && preg_match($regexp, $mime, $matches)) {
                    $mimetype = $matches[1];
                }
            }
        }
        /* This is an ugly hack, but UNIX-type systems provide a "native" way to detect the file type,
         * which is still more secure than depending on the value of $_FILES[$field]['type'], and as it
         * was reported in issue #750 (https://github.com/EllisLab/CodeIgniter/issues/750) - it's better
         * than mime_content_type() as well, hence the attempts to try calling the command line with
         * three different functions.
         *
         * Notes:
         *  - the DIRECTORY_SEPARATOR comparison ensures that we're not on a Windows system
         *  - many system admins would disable the exec(), shell_exec(), popen() and similar functions
         *    due to security concerns, hence the function_usable() checks
         */
        if (DIRECTORY_SEPARATOR !== '\\') {
            $cmd = 'file --brief --mime ' . escapeshellarg($filepath) . ' 2>&1';
            if (empty($mimetype) && function_exists('exec')) {
                /* This might look confusing, as $mime is being populated with all of the output
                 * when set in the second parameter. However, we only need the last line, which is
                 * the actual return value of exec(), and as such - it overwrites anything that could
                 * already be set for $mime previously. This effectively makes the second parameter a
                 * dummy value, which is only put to allow us to get the return status code.
                 */
                $mime = @exec($cmd, $mime, $return_status);
                if ($return_status === 0 && is_string($mime) && preg_match($regexp, $mime, $matches)) {
                    $mimetype = $matches[1];
                }
            }
            if (empty($mimetype) && function_exists('shell_exec')) {
                $mime = @shell_exec($cmd);
                if (strlen($mime) > 0) {
                    $mime = explode("\n", trim($mime));
                    if (preg_match($regexp, $mime[(count($mime) - 1)], $matches)) {
                        $mimetype = $matches[1];
                    }
                }
            }
            if (empty($mimetype) && function_exists('popen')) {
                $proc = @popen($cmd, 'r');
                if (is_resource($proc)) {
                    $mime = @fread($proc, 512);
                    @pclose($proc);
                    if ($mime !== false) {
                        $mime = explode("\n", trim($mime));
                        if (preg_match($regexp, $mime[(count($mime) - 1)], $matches)) {
                            $mimetype = $matches[1];
                        }
                    }
                }
            }
        }
        // Fall back to mime_content_type(), if available (still better than $_FILES[$field]['type'])
        if (empty($mimetype) && function_exists('mime_content_type')) {
            $mimetype = @mime_content_type($filepath);
            // It's possible that mime_content_type() returns FALSE or an empty string
            if ($mimetype == false && strlen($mimetype) > 0) {
                throw new ServerException(_m('Could not determine file\'s MIME type.'));
            }
        }

        // Unclear types are such that we can't really tell by the auto
        // detect what they are (.bin, .exe etc. are just "octet-stream")
        $unclearTypes = ['application/octet-stream',
            'application/vnd.ms-office',
            'application/zip',
            'text/plain',
            'text/html',  // Ironically, Wikimedia Commons' SVG_logo.svg is identified as text/html
            // TODO: for XML we could do better content-based sniffing too
            'text/xml',];

        $supported = common_config('attachments', 'supported');

        // If we didn't match, or it is an unclear match
        if ($originalFilename && (!$mimetype || in_array($mimetype, $unclearTypes))) {
            try {
                $type = common_supported_filename_to_mime($originalFilename);
                return $type;
            } catch (UnknownExtensionMimeException $e) {
                // FIXME: I think we should keep the file extension here (supported should be === true here)
            } catch (Exception $e) {
                // Extension parsed but no connected mimetype, so $mimetype is our best guess
            }
        }

        // If $config['attachments']['supported'] equals boolean true, accept any mimetype
        if ($supported === true || array_key_exists($mimetype, $supported)) {
            // FIXME: Don't know if it always has a mimetype here because
            // finfo->file CAN return false on error: http://php.net/finfo_file
            // so if $supported === true, this may return something unexpected.
            return $mimetype;
        }

        // We can conclude that we have failed to get the MIME type
        $media = common_get_mime_media($mimetype);
        if ('application' !== $media) {
            // TRANS: Client exception thrown trying to upload a forbidden MIME type.
            // TRANS: %1$s is the file type that was denied, %2$s is the application part of
            // TRANS: the MIME type that was denied.
            $hint = sprintf(_m('"%1$s" is not a supported file type on this server. ' .
                'Try using another %2$s format.'), $mimetype, $media);
        } else {
            // TRANS: Client exception thrown trying to upload a forbidden MIME type.
            // TRANS: %s is the file type that was denied.
            $hint = sprintf(_m('"%s" is not a supported file type on this server.'), $mimetype);
        }
        throw new ClientException($hint);
    }

    /**
     * Title for a file, to display in the interface (if there's no better title) and
     * for download filenames
     *
     * @param $file File object
     * @returns string
     */
    public static function getDisplayName(File $file): string
    {
        if (empty($file->filename)) {
            return _m('Untitled attachment');
        }

        // New file name format is "{bin2hex(original_name.ext)}-{$hash}"
        $filename = self::decodeFilename($file->filename);

        // If there was an error in the match, something's wrong with some piece
        // of code (could be a file with utf8 chars in the name)
        $log_error_msg = "Invalid file name for File with id={$file->id} " .
            "({$file->filename}). Some plugin probably did something wrong.";
        if ($filename === false) {
            common_log(LOG_ERR, $log_error_msg);
        } elseif ($filename === null) {
            // The old file name format was "{hash}.{ext}" so we didn't have a name
            // This extracts the extension
            $ret = preg_match('/^.+?\.+?(.+)$/', $file->filename, $matches);
            if ($ret !== 1) {
                common_log(LOG_ERR, $log_error_msg);
                return _m('Untitled attachment');
            }
            $ext = $matches[1];
            // There's a blacklisted extension array, which could have an alternative
            // extension, such as phps, to replace php. We want to turn it back
            // (currently defaulted to empty, but let's keep the feature)
            $blacklist = common_config('attachments', 'extblacklist');
            if (is_array($blacklist)) {
                foreach ($blacklist as $upload_ext => $safe_ext) {
                    if ($ext === $safe_ext) {
                        $ext = $upload_ext;
                        break;
                    }
                }
            }
            $filename = "untitled.{$ext}";
        }
        return $filename;
    }
}
