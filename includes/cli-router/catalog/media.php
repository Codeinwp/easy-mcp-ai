<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'media fix-orientation' => array(
        'command' => 'media fix-orientation',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<attachment-id>...]
: One or more IDs of the attachments to regenerate.

[--dry-run]
: Check images needing orientation without performing the operation.

## EXAMPLES

    # Fix orientation for all images.
    $ wp media fix-orientation
    1/3 Fixing orientation for "Landscape_4" (ID 62).
    2/3 Fixing orientation for "Landscape_3" (ID 61).
    3/3 Fixing orientation for "Landscape_2" (ID 60).
    Success: Fixed 3 of 3 images.

    # Fix orientation dry run.
    $ wp media fix-orientation 63 --dry-run
    1/1 "Portrait_6" (ID 63) will be affected.
    Success: 1 of 1 image will be affected.

    # Fix orientation for specific images.
    $ wp media fix-orientation 63
    1/1 Fixing orientation for "Portrait_6" (ID 63).
    Success: Fixed 1 of 1 images.',
        'name' => 'fix-orientation',
        'params' => array(
            array(
                'name' => 'attachment-id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'dry-run',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Fix image orientation for one or more attachments.',
        'synopsis' => '[<attachment-id>...] [--dry-run]',
    ),
    'media image-size' => array(
        'command' => 'media image-size',
        'fields' => array(
            'default' => array(
                'name',
                'width',
                'height',
                'crop',
                'ratio',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--fields=<fields>]
: Limit the output to specific fields. Defaults to all fields.

[--format=<format>]
: Render output in a specific format
---
default: table
options:
  - table
  - json
  - csv
  - yaml
  - count
---

## AVAILABLE FIELDS

These fields will be displayed by default for each image size:
* name
* width
* height
* crop
* ratio

## EXAMPLES

    # List all registered image sizes
    $ wp media image-size
    +---------------------------+-------+--------+-------+-------+
    | name                      | width | height | crop  | ratio |
    +---------------------------+-------+--------+-------+-------+
    | full                      |       |        | N/A   | N/A   |
    | twentyfourteen-full-width | 1038  | 576    | hard  | 173:96|
    | large                     | 1024  | 1024   | soft  | N/A   |
    | medium_large              | 768   | 0      | soft  | N/A   |
    | medium                    | 300   | 300    | soft  | N/A   |
    | thumbnail                 | 150   | 150    | hard  | 1:1   |
    +---------------------------+-------+--------+-------+-------+',
        'name' => 'image-size',
        'params' => array(
            array(
                'desc' => 'Limit the output to specific fields. Defaults to all fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a specific format',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'json',
                    'csv',
                    'yaml',
                    'count',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists image sizes registered with WordPress.',
        'synopsis' => '[--fields=<fields>] [--format=<format>]',
    ),
    'media import' => array(
        'command' => 'media import',
        'fields' => null,
        'longdesc' => '## OPTIONS

<file>...
: Path to file or files to be imported. Supports the glob(3) capabilities of the current shell.
    If file is recognized as a URL (for example, with a scheme of http or ftp), the file will be
    downloaded to a temp file before being sideloaded.

[--post_id=<post_id>]
: ID of the post to attach the imported files to.

[--post_name=<post_name>]
: Name of the post to attach the imported files to.

[--file_name=<name>]
: Attachment name (post_name field).

[--title=<title>]
: Attachment title (post title field).

[--caption=<caption>]
: Caption for attachment (post excerpt field).

[--alt=<alt_text>]
: Alt text for image (saved as post meta).

[--desc=<description>]
: "Description" field (post content) of attachment post.

[--skip-copy]
: If set, media files (local only) are imported to the library but not moved on disk.
File names will not be run through wp_unique_filename() with this set.

[--preserve-filetime]
: Use the file modified time as the post published & modified dates.
Remote files will always use the current time.

[--featured_image]
: If set, set the imported image as the Featured Image of the post it is attached to.

[--porcelain[=<field>]]
: Output a single field for each imported image. Defaults to attachment ID when used as flag.
---
options:
  - url
---

## EXAMPLES

    # Import all jpgs in the current user\'s "Pictures" directory, not attached to any post.
    $ wp media import ~/Pictures/**\\/*.jpg
    Imported file \'/home/person/Pictures/landscape-photo.jpg\' as attachment ID 1751.
    Imported file \'/home/person/Pictures/fashion-icon.jpg\' as attachment ID 1752.
    Success: Imported 2 of 2 items.

    # Import a local image and set it to be the post thumbnail for a post.
    $ wp media import ~/Downloads/image.png --post_id=123 --title="A downloaded picture" --featured_image
    Imported file \'/home/person/Downloads/image.png\' as attachment ID 1753 and attached to post 123 as featured image.
    Success: Imported 1 of 1 images.

    # Import a local image, but set it as the featured image for all posts.
    # 1. Import the image and get its attachment ID.
    # 2. Assign the attachment ID as the featured image for all posts.
    $ ATTACHMENT_ID="$(wp media import ~/Downloads/image.png --porcelain)"
    $ wp post list --post_type=post --format=ids | xargs -d \' \' -I % wp post meta add % _thumbnail_id $ATTACHMENT_ID
    Success: Added custom field.
    Success: Added custom field.

    # Import an image from the web.
    $ wp media import http://s.wordpress.org/style/images/wp-header-logo.png --title=\'The WordPress logo\' --alt="Semantic personal publishing"
    Imported file \'http://s.wordpress.org/style/images/wp-header-logo.png\' as attachment ID 1755.
    Success: Imported 1 of 1 images.

    # Get the URL for an attachment after import.
    $ wp media import http://s.wordpress.org/style/images/wp-header-logo.png --porcelain | xargs -I {} wp post list --post__in={} --field=url --post_type=attachment
    http://wordpress-develop.dev/wp-header-logo/',
        'name' => 'import',
        'params' => array(
            array(
                'name' => 'file',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'ID of the post to attach the imported files to.',
                'name' => 'post_id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Name of the post to attach the imported files to.',
                'name' => 'post_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Attachment name (post_name field).',
                'name' => 'file_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Attachment title (post title field).',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Caption for attachment (post excerpt field).',
                'name' => 'caption',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Alt text for image (saved as post meta).',
                'name' => 'alt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => '"Description" field (post content) of attachment post.',
                'name' => 'desc',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-copy',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'preserve-filetime',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'featured_image',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => '',
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => true,
            ),
        ),
        'shortdesc' => 'Creates attachments from local files or URLs.',
        'synopsis' => '<file>... [--post_id=<post_id>] [--post_name=<post_name>] [--file_name=<name>] [--title=<title>] [--caption=<caption>] [--alt=<alt_text>] [--desc=<description>] [--skip-copy] [--preserve-filetime] [--featured_image] [--porcelain[=<field>]]',
    ),
    'media regenerate' => array(
        'command' => 'media regenerate',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<attachment-id>...]
: One or more IDs of the attachments to regenerate.

[--image_size=<image_size>]
: Name of the image size to regenerate. Only thumbnails of this image size will be regenerated, thumbnails of other image sizes will not.

[--skip-delete]
: Skip deletion of the original thumbnails. If your thumbnails are linked from sources outside your control, it\'s likely best to leave them around. Defaults to false.

[--only-missing]
: Only generate thumbnails for images missing image sizes.

[--delete-unknown]
: Only delete thumbnails for old unregistered image sizes.

[--yes]
: Answer yes to the confirmation message. Confirmation only shows when no IDs passed as arguments.

## EXAMPLES

    # Regenerate thumbnails for given attachment IDs.
    $ wp media regenerate 123 124 125
    Found 3 images to regenerate.
    1/3 Regenerated thumbnails for "Vertical Image" (ID 123).
    2/3 Regenerated thumbnails for "Horizontal Image" (ID 124).
    3/3 Regenerated thumbnails for "Beautiful Picture" (ID 125).
    Success: Regenerated 3 of 3 images.

    # Regenerate all thumbnails, without confirmation.
    $ wp media regenerate --yes
    Found 3 images to regenerate.
    1/3 Regenerated thumbnails for "Sydney Harbor Bridge" (ID 760).
    2/3 Regenerated thumbnails for "Boardwalk" (ID 757).
    3/3 Regenerated thumbnails for "Sunburst Over River" (ID 756).
    Success: Regenerated 3 of 3 images.

    # Re-generate all thumbnails that have IDs between 1000 and 2000.
    $ seq 1000 2000 | xargs wp media regenerate
    Found 4 images to regenerate.
    1/4 Regenerated thumbnails for "Vertical Featured Image" (ID 1027).
    2/4 Regenerated thumbnails for "Horizontal Featured Image" (ID 1022).
    3/4 Regenerated thumbnails for "Unicorn Wallpaper" (ID 1045).
    4/4 Regenerated thumbnails for "I Am Worth Loving Wallpaper" (ID 1023).
    Success: Regenerated 4 of 4 images.

    # Re-generate only the thumbnails of "large" image size for all images.
    $ wp media regenerate --image_size=large
    Do you really want to regenerate the "large" image size for all images? [y/n] y
    Found 3 images to regenerate.
    1/3 Regenerated "large" thumbnail for "Sydney Harbor Bridge" (ID 760).
    2/3 No "large" thumbnail regeneration needed for "Boardwalk" (ID 757).
    3/3 Regenerated "large" thumbnail for "Sunburst Over River" (ID 756).
    Success: Regenerated 3 of 3 images.',
        'name' => 'regenerate',
        'params' => array(
            array(
                'name' => 'attachment-id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Name of the image size to regenerate. Only thumbnails of this image size will be regenerated, thumbnails of other image sizes will not.',
                'name' => 'image_size',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-delete',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'only-missing',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'delete-unknown',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'yes',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Regenerates thumbnails for one or more attachments.',
        'synopsis' => '[<attachment-id>...] [--image_size=<image_size>] [--skip-delete] [--only-missing] [--delete-unknown] [--yes]',
    ),
);
