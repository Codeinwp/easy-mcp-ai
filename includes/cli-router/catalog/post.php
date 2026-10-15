<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'post create' => array(
        'command' => 'post create',
        'fields' => null,
        'longdesc' => '## OPTIONS

[--post_author=<post_author>]
: The ID of the user who added the post. Default is the current user ID.

[--post_date=<post_date>]
: The date of the post. Default is the current time.

[--post_date_gmt=<post_date_gmt>]
: The date of the post in the GMT timezone. Default is the value of $post_date.

[--post_content=<post_content>]
: The post content. Default empty.

[--post_content_filtered=<post_content_filtered>]
: The filtered post content. Default empty.

[--post_title=<post_title>]
: The post title. Default empty.

[--post_excerpt=<post_excerpt>]
: The post excerpt. Default empty.

[--post_status=<post_status>]
: The post status. Default \'draft\'.

[--post_type=<post_type>]
: The post type. Default \'post\'.

[--comment_status=<comment_status>]
: Whether the post can accept comments. Accepts \'open\' or \'closed\'. Default is the value of \'default_comment_status\' option.

[--ping_status=<ping_status>]
: Whether the post can accept pings. Accepts \'open\' or \'closed\'. Default is the value of \'default_ping_status\' option.

[--post_password=<post_password>]
: The password to access the post. Default empty.

[--post_name=<post_name>]
: The post name. Default is the sanitized post title when creating a new post.

[--from-post=<post_id>]
: Post id of a post to be duplicated.

[--to_ping=<to_ping>]
: Space or carriage return-separated list of URLs to ping. Default empty.

[--pinged=<pinged>]
: Space or carriage return-separated list of URLs that have been pinged. Default empty.

[--post_modified=<post_modified>]
: The date when the post was last modified. Default is the current time.

[--post_modified_gmt=<post_modified_gmt>]
: The date when the post was last modified in the GMT timezone. Default is the current time.

[--post_parent=<post_parent>]
: Set this for the post it belongs to, if any. Default 0.

[--menu_order=<menu_order>]
: The order the post should be displayed in. Default 0.

[--post_mime_type=<post_mime_type>]
: The mime type of the post. Default empty.

[--guid=<guid>]
: Global Unique ID for referencing the post. Default empty.

[--post_category=<post_category>]
: Array of category names, slugs, or IDs. Defaults to value of the \'default_category\' option.

[--tags_input=<tags_input>]
: Array of tag names, slugs, or IDs. Default empty.

[--tax_input=<tax_input>]
: Array of taxonomy terms keyed by their taxonomy name. Default empty.

[--meta_input=<meta_input>]
: Array in JSON format of post meta values keyed by their post meta key. Default empty.

[<file>]
: Read post content from <file>. If this value is present, the
    `--post_content` argument will be ignored.

  Passing `-` as the filename will cause post content to
  be read from STDIN.

[--<field>=<value>]
: Associative args for the new post. See wp_insert_post().

[--edit]
: Immediately open system\'s editor to write or edit post content.

  If content is read from a file, from STDIN, or from the `--post_content`
  argument, that text will be loaded into the editor.

[--porcelain]
: Output just the new post id.


## EXAMPLES

    # Create post and schedule for future
    $ wp post create --post_type=post --post_title=\'A future post\' --post_status=future --post_date=\'2030-12-01 07:00:00\'
    Success: Created post 1921.

    # Create post with content from given file
    $ wp post create ./post-content.txt --post_category=201,345 --post_title=\'Post from file\'
    Success: Created post 1922.

    # Create a post with multiple meta values.
    $ wp post create --post_title=\'A post\' --post_content=\'Just a small post.\' --meta_input=\'{"key1":"value1","key2":"value2"}\'
    Success: Created post 1923.

    # Create a duplicate post from existing posts.
    $ wp post create --from-post=123 --post_title=\'Different Title\'
    Success: Created post 2350.',
        'name' => 'create',
        'params' => array(
            array(
                'desc' => 'The ID of the user who added the post. Default is the current user ID.',
                'name' => 'post_author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date of the post. Default is the current time.',
                'name' => 'post_date',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date of the post in the GMT timezone. Default is the value of $post_date.',
                'name' => 'post_date_gmt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post content. Default empty.',
                'name' => 'post_content',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The filtered post content. Default empty.',
                'name' => 'post_content_filtered',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post title. Default empty.',
                'name' => 'post_title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post excerpt. Default empty.',
                'name' => 'post_excerpt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post status. Default \'draft\'.',
                'name' => 'post_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post type. Default \'post\'.',
                'name' => 'post_type',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Whether the post can accept comments. Accepts \'open\' or \'closed\'. Default is the value of \'default_comment_status\' option.',
                'name' => 'comment_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Whether the post can accept pings. Accepts \'open\' or \'closed\'. Default is the value of \'default_ping_status\' option.',
                'name' => 'ping_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The password to access the post. Default empty.',
                'name' => 'post_password',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post name. Default is the sanitized post title when creating a new post.',
                'name' => 'post_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Post id of a post to be duplicated.',
                'name' => 'from-post',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Space or carriage return-separated list of URLs to ping. Default empty.',
                'name' => 'to_ping',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Space or carriage return-separated list of URLs that have been pinged. Default empty.',
                'name' => 'pinged',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date when the post was last modified. Default is the current time.',
                'name' => 'post_modified',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date when the post was last modified in the GMT timezone. Default is the current time.',
                'name' => 'post_modified_gmt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set this for the post it belongs to, if any. Default 0.',
                'name' => 'post_parent',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The order the post should be displayed in. Default 0.',
                'name' => 'menu_order',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The mime type of the post. Default empty.',
                'name' => 'post_mime_type',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Global Unique ID for referencing the post. Default empty.',
                'name' => 'guid',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array of category names, slugs, or IDs. Defaults to value of the \'default_category\' option.',
                'name' => 'post_category',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array of tag names, slugs, or IDs. Default empty.',
                'name' => 'tags_input',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array of taxonomy terms keyed by their taxonomy name. Default empty.',
                'name' => 'tax_input',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array in JSON format of post meta values keyed by their post meta key. Default empty.',
                'name' => 'meta_input',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'file',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'name' => 'edit',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Creates a new post.',
        'synopsis' => '[--post_author=<post_author>] [--post_date=<post_date>] [--post_date_gmt=<post_date_gmt>] [--post_content=<post_content>] [--post_content_filtered=<post_content_filtered>] [--post_title=<post_title>] [--post_excerpt=<post_excerpt>] [--post_status=<post_status>] [--post_type=<post_type>] [--comment_status=<comment_status>] [--ping_status=<ping_status>] [--post_password=<post_password>] [--post_name=<post_name>] [--from-post=<post_id>] [--to_ping=<to_ping>] [--pinged=<pinged>] [--post_modified=<post_modified>] [--post_modified_gmt=<post_modified_gmt>] [--post_parent=<post_parent>] [--menu_order=<menu_order>] [--post_mime_type=<post_mime_type>] [--guid=<guid>] [--post_category=<post_category>] [--tags_input=<tags_input>] [--tax_input=<tax_input>] [--meta_input=<meta_input>] [<file>] [--<field>=<value>] [--edit] [--porcelain]',
    ),
    'post delete' => array(
        'command' => 'post delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: One or more IDs of posts to delete.

[--force]
: Skip the trash bin.

[--defer-term-counting]
: Recalculate term count in batch, for a performance boost.

## EXAMPLES

    # Delete post skipping trash
    $ wp post delete 123 --force
    Success: Deleted post 123.

    # Delete multiple posts
    $ wp post delete 123 456 789
    Success: Trashed post 123.
    Success: Trashed post 456.
    Success: Trashed post 789.

    # Delete all pages
    $ wp post delete $(wp post list --post_type=\'page\' --format=ids)
    Success: Trashed post 1164.
    Success: Trashed post 1186.

    # Delete all posts in the trash
    $ wp post delete $(wp post list --post_status=trash --format=ids)
    Success: Deleted post 1268.
    Success: Deleted post 1294.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'defer-term-counting',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Deletes an existing post.',
        'synopsis' => '<id>... [--force] [--defer-term-counting]',
    ),
    'post edit' => array(
        'command' => 'post edit',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the post to edit.

## EXAMPLES

    # Launch system editor to edit post
    $ wp post edit 123',
        'name' => 'edit',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Launches system editor to edit post content.',
        'synopsis' => '<id>',
    ),
    'post exists' => array(
        'command' => 'post exists',
        'fields' => null,
        'longdesc' => 'Displays a success message if the post does exist.

## OPTIONS

<id>
: The ID of the post to check.

## EXAMPLES

    # The post exists.
    $ wp post exists 1337
    Success: Post with ID 1337 exists.
    $ echo $?
    0

    # The post does not exist.
    $ wp post exists 10000
    $ echo $?
    1',
        'name' => 'exists',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Verifies whether a post exists.',
        'synopsis' => '<id>',
    ),
    'post generate' => array(
        'command' => 'post generate',
        'fields' => null,
        'longdesc' => 'Creates a specified number of new posts with dummy data.

## OPTIONS

[--count=<number>]
: How many posts to generate?
---
default: 100
---

[--post_type=<type>]
: The type of the generated posts.
---
default: post
---

[--post_status=<status>]
: The status of the generated posts.
---
default: publish
---

[--post_title=<post_title>]
: The post title.
---
default:
---

[--post_author=<login>]
: The author of the generated posts.
---
default:
---

[--post_date=<yyyy-mm-dd-hh-ii-ss>]
: The date of the post. Default is the current time.

[--post_date_gmt=<yyyy-mm-dd-hh-ii-ss>]
: The date of the post in the GMT timezone. Default is the value of --post_date.

[--post_content]
: If set, the command reads the post_content from STDIN.

[--max_depth=<number>]
: For hierarchical post types, generate child posts down to a certain depth.
---
default: 1
---

[--format=<format>]
: Render output in a particular format.
---
default: progress
options:
  - progress
  - ids
---

## EXAMPLES

    # Generate posts.
    $ wp post generate --count=10 --post_type=page --post_date=1999-01-04
    Generating posts  100% [================================================] 0:01 / 0:04

    # Generate posts with fetched content.
    $ curl -N https://loripsum.net/api/5 | wp post generate --post_content --count=10
      % Total    % Received % Xferd  Average Speed   Time    Time     Time  Current
                                     Dload  Upload   Total   Spent    Left  Speed
    100  2509  100  2509    0     0    616      0  0:00:04  0:00:04 --:--:--   616
    Generating posts  100% [================================================] 0:01 / 0:04

    # Add meta to every generated posts.
    $ wp post generate --format=ids | xargs -d \' \' -I % wp post meta add % foo bar
    Success: Added custom field.
    Success: Added custom field.
    Success: Added custom field.',
        'name' => 'generate',
        'params' => array(
            array(
                'default' => 100,
                'desc' => 'How many posts to generate?',
                'name' => 'count',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'post',
                'desc' => 'The type of the generated posts.',
                'name' => 'post_type',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'publish',
                'desc' => 'The status of the generated posts.',
                'name' => 'post_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => '',
                'desc' => 'The post title.',
                'name' => 'post_title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => '',
                'desc' => 'The author of the generated posts.',
                'name' => 'post_author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date of the post. Default is the current time.',
                'name' => 'post_date',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date of the post in the GMT timezone. Default is the value of --post_date.',
                'name' => 'post_date_gmt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'post_content',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 1,
                'desc' => 'For hierarchical post types, generate child posts down to a certain depth.',
                'name' => 'max_depth',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'progress',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'progress',
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Generates some posts.',
        'synopsis' => '[--count=<number>] [--post_type=<type>] [--post_status=<status>] [--post_title=<post_title>] [--post_author=<login>] [--post_date=<yyyy-mm-dd-hh-ii-ss>] [--post_date_gmt=<yyyy-mm-dd-hh-ii-ss>] [--post_content] [--max_depth=<number>] [--format=<format>]',
    ),
    'post get' => array(
        'command' => 'post get',
        'fields' => array(
            'default' => array(
                'ID',
                'post_author',
                'post_date',
                'post_date_gmt',
                'post_content',
                'post_title',
                'post_excerpt',
                'post_status',
                'comment_status',
                'ping_status',
                'post_password',
                'post_name',
                'to_ping',
                'pinged',
                'post_modified',
                'post_modified_gmt',
                'post_content_filtered',
                'post_parent',
                'guid',
                'menu_order',
                'post_type',
                'post_mime_type',
                'comment_count',
                'url',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<id>
: The ID of the post to get.

[--field=<field>]
: Instead of returning the whole post, returns the value of a single field.

[--fields=<fields>]
: Limit the output to specific fields. Defaults to all fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
---

## EXAMPLES

    # Save the post content to a file
    $ wp post get 123 --field=content > file.txt',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole post, returns the value of a single field.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
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
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets details about a post.',
        'synopsis' => '<id> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'post list' => array(
        'command' => 'post list',
        'fields' => array(
            'default' => array(
                'ID',
                'post_title',
                'post_name',
                'post_date',
                'post_status',
            ),
            'optional' => array(
                'post_author',
                'post_date_gmt',
                'post_content',
                'post_excerpt',
                'comment_status',
                'ping_status',
                'post_password',
                'to_ping',
                'pinged',
                'post_modified',
                'post_modified_gmt',
                'post_content_filtered',
                'post_parent',
                'guid',
                'menu_order',
                'post_type',
                'post_mime_type',
                'comment_count',
                'filter',
                'url',
            ),
        ),
        'longdesc' => 'Display posts based on all arguments supported by [WP_Query()](https://developer.wordpress.org/reference/classes/wp_query/).
Only shows post types marked as post by default.

## OPTIONS

[--<field>=<value>]
: One or more args to pass to WP_Query.

[--field=<field>]
: Prints the value of a single field for each post.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - ids
  - json
  - count
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each post:

* ID
* post_title
* post_name
* post_date
* post_status

These fields are optionally available:

* post_author
* post_date_gmt
* post_content
* post_excerpt
* comment_status
* ping_status
* post_password
* to_ping
* pinged
* post_modified
* post_modified_gmt
* post_content_filtered
* post_parent
* guid
* menu_order
* post_type
* post_mime_type
* comment_count
* filter
* url

## EXAMPLES

    # List post
    $ wp post list --field=ID
    568
    829
    1329
    1695

    # List posts in JSON
    $ wp post list --post_type=post --posts_per_page=5 --format=json
    [{"ID":1,"post_title":"Hello world!","post_name":"hello-world","post_date":"2015-06-20 09:00:10","post_status":"publish"},{"ID":1178,"post_title":"Markup: HTML Tags and Formatting","post_name":"markup-html-tags-and-formatting","post_date":"2013-01-11 20:22:19","post_status":"draft"}]

    # List all pages
    $ wp post list --post_type=page --fields=post_title,post_status
    +-------------+-------------+
    | post_title  | post_status |
    +-------------+-------------+
    | Sample Page | publish     |
    +-------------+-------------+

    # List ids of all pages and posts
    $ wp post list --post_type=page,post --format=ids
    15 25 34 37 198

    # List given posts
    $ wp post list --post__in=1,3
    +----+--------------+-------------+---------------------+-------------+
    | ID | post_title   | post_name   | post_date           | post_status |
    +----+--------------+-------------+---------------------+-------------+
    | 3  | Lorem Ipsum  | lorem-ipsum | 2016-06-01 14:34:36 | publish     |
    | 1  | Hello world! | hello-world | 2016-06-01 14:31:12 | publish     |
    +----+--------------+-------------+---------------------+-------------+

    # List given post by a specific author
    $ wp post list --author=2
    +----+-------------------+-------------------+---------------------+-------------+
    | ID | post_title        | post_name         | post_date           | post_status |
    +----+-------------------+-------------------+---------------------+-------------+
    | 14 | New documentation | new-documentation | 2021-06-18 21:05:11 | publish     |
    +----+-------------------+-------------------+---------------------+-------------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each post.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific object fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'ids',
                    'json',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a list of posts.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'post meta add' => array(
        'command' => 'post meta add',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to create.

[<value>]
: The value of the meta field. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Add a meta field.',
        'synopsis' => '<id> <key> [<value>] [--format=<format>]',
    ),
    'post meta clean-duplicates' => array(
        'command' => 'post meta clean-duplicates',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: ID of the post to clean.

<key>
: Meta key to clean up.

## EXAMPLES

    # Delete duplicate post meta.
    wp post meta clean-duplicates 1234 enclosure
    Success: Cleaned up duplicate \'enclosure\' meta values.',
        'name' => 'clean-duplicates',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Cleans up duplicate post meta values on a post.',
        'synopsis' => '<id> <key>',
    ),
    'post meta delete' => array(
        'command' => 'post meta delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

[<key>]
: The name of the meta field to delete.

[<value>]
: The value to delete. If omitted, all rows with key will deleted.

[--all]
: Delete all meta for the object.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Delete a meta field.',
        'synopsis' => '<id> [<key>] [<value>] [--all]',
    ),
    'post meta get' => array(
        'command' => 'post meta get',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to get.

[--single]
: Whether to return a single value.

[--format=<format>]
: Get value in a particular format.
---
default: var_export
options:
  - var_export
  - json
  - yaml
---',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'single',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 'var_export',
                'desc' => 'Get value in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'var_export',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Get meta field value.',
        'synopsis' => '<id> <key> [--single] [--format=<format>]',
    ),
    'post meta list' => array(
        'command' => 'post meta list',
        'fields' => array(
            'default' => array(
                'post_id',
                'meta_key',
                'meta_value',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<id>
: ID for the object.

[--keys=<keys>]
: Limit output to metadata of specific keys.

[--fields=<fields>]
: Limit the output to specific row fields. Defaults to id,meta_key,meta_value.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
  - count
---

[--orderby=<fields>]
: Set orderby which field.
---
default: id
options:
 - id
 - meta_key
 - meta_value
---

[--order=<order>]
: Set ascending or descending order.
---
default: asc
options:
 - asc
 - desc
---

[--unserialize]
: Unserialize meta_value output.',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Limit output to metadata of specific keys.',
                'name' => 'keys',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific row fields. Defaults to id,meta_key,meta_value.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'json',
                    'yaml',
                    'count',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'id',
                'desc' => 'Set orderby which field.',
                'name' => 'orderby',
                'optional' => true,
                'options' => array(
                    'id',
                    'meta_key',
                    'meta_value',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'asc',
                'desc' => 'Set ascending or descending order.',
                'name' => 'order',
                'optional' => true,
                'options' => array(
                    'asc',
                    'desc',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'unserialize',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'List all metadata associated with an object.',
        'synopsis' => '<id> [--keys=<keys>] [--fields=<fields>] [--format=<format>] [--orderby=<fields>] [--order=<order>] [--unserialize]',
    ),
    'post meta patch' => array(
        'command' => 'post meta patch',
        'fields' => null,
        'longdesc' => '## OPTIONS

<action>
: Patch action to perform.
---
options:
  - insert
  - update
  - delete
---

<id>
: The ID of the object.

<key>
: The name of the meta field to update.

<key-path>...
: The name(s) of the keys within the value to locate the value to patch.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'patch',
        'params' => array(
            array(
                'name' => 'action',
                'optional' => false,
                'options' => array(
                    'insert',
                    'update',
                    'delete',
                ),
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Update a nested value for a meta field.',
        'synopsis' => '<action> <id> <key> <key-path>... [<value>] [--format=<format>]',
    ),
    'post meta pluck' => array(
        'command' => 'post meta pluck',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to get.

<key-path>...
: The name(s) of the keys within the value to locate the value to pluck.

[--format=<format>]
: The output format of the value.
---
default: plaintext
options:
  - plaintext
  - json
  - yaml',
        'name' => 'pluck',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The output format of the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Get a nested value from a meta field.',
        'synopsis' => '<id> <key> <key-path>... [--format=<format>]',
    ),
    'post meta update' => array(
        'alias' => 'set',
        'command' => 'post meta update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to update.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Update a meta field.',
        'synopsis' => '<id> <key> [<value>] [--format=<format>]',
    ),
    'post term add' => array(
        'command' => 'post term add',
        'fields' => null,
        'longdesc' => 'Append the term to the existing set of terms on the object.

## OPTIONS

<id>
: The ID of the object.

<taxonomy>
: The name of the taxonomy type to be added.

<term>...
: The slug of the term or terms to be added.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: slug
options:
  - slug
  - id
---',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'slug',
                'desc' => 'Explicitly handle the term value as a slug or id.',
                'name' => 'by',
                'optional' => true,
                'options' => array(
                    'slug',
                    'id',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Add a term to an object.',
        'synopsis' => '<id> <taxonomy> <term>... [--by=<field>]',
    ),
    'post term list' => array(
        'command' => 'post term list',
        'fields' => array(
            'default' => array(
                'term_id',
                'name',
                'slug',
                'taxonomy',
            ),
            'optional' => array(
                'term_taxonomy_id',
                'description',
                'term_group',
                'parent',
                'count',
            ),
        ),
        'longdesc' => '## OPTIONS

<id>
: ID for the object.

<taxonomy>...
: One or more taxonomies to list.

[--field=<field>]
: Prints the value of a single field for each term.

[--fields=<fields>]
: Limit the output to specific row fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
  - count
  - ids
---

## AVAILABLE FIELDS

These fields will be displayed by default for each term:

* term_id
* name
* slug
* taxonomy

These fields are optionally available:

* term_taxonomy_id
* description
* term_group
* parent
* count',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Prints the value of a single field for each term.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific row fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'json',
                    'yaml',
                    'count',
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'List all terms associated with an object.',
        'synopsis' => '<id> <taxonomy>... [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'post term remove' => array(
        'command' => 'post term remove',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<taxonomy>
: The name of the term\'s taxonomy.

[<term>...]
: The slug of the term or terms to be removed from the object.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: slug
options:
  - slug
  - id
---

[--all]
: Remove all terms from the object.',
        'name' => 'remove',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'slug',
                'desc' => 'Explicitly handle the term value as a slug or id.',
                'name' => 'by',
                'optional' => true,
                'options' => array(
                    'slug',
                    'id',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Remove a term from an object.',
        'synopsis' => '<id> <taxonomy> [<term>...] [--by=<field>] [--all]',
    ),
    'post term set' => array(
        'command' => 'post term set',
        'fields' => null,
        'longdesc' => 'Replaces existing terms on the object.

## OPTIONS

<id>
: The ID of the object.

<taxonomy>
: The name of the taxonomy type to be updated.

<term>...
: The slug of the term or terms to be updated.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: slug
options:
  - slug
  - id
---',
        'name' => 'set',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'slug',
                'desc' => 'Explicitly handle the term value as a slug or id.',
                'name' => 'by',
                'optional' => true,
                'options' => array(
                    'slug',
                    'id',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Set object terms.',
        'synopsis' => '<id> <taxonomy> <term>... [--by=<field>]',
    ),
    'post update' => array(
        'command' => 'post update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: One or more IDs of posts to update.

[--post_author=<post_author>]
: The ID of the user who added the post. Default is the current user ID.

[--post_date=<post_date>]
: The date of the post. Default is the current time.

[--post_date_gmt=<post_date_gmt>]
: The date of the post in the GMT timezone. Default is the value of $post_date.

[--post_content=<post_content>]
: The post content. Default empty.

[--post_content_filtered=<post_content_filtered>]
: The filtered post content. Default empty.

[--post_title=<post_title>]
: The post title. Default empty.

[--post_excerpt=<post_excerpt>]
: The post excerpt. Default empty.

[--post_status=<post_status>]
: The post status. Default \'draft\'.

[--post_type=<post_type>]
: The post type. Default \'post\'.

[--comment_status=<comment_status>]
: Whether the post can accept comments. Accepts \'open\' or \'closed\'. Default is the value of \'default_comment_status\' option.

[--ping_status=<ping_status>]
: Whether the post can accept pings. Accepts \'open\' or \'closed\'. Default is the value of \'default_ping_status\' option.

[--post_password=<post_password>]
: The password to access the post. Default empty.

[--post_name=<post_name>]
: The post name. Default is the sanitized post title when creating a new post.

[--to_ping=<to_ping>]
: Space or carriage return-separated list of URLs to ping. Default empty.

[--pinged=<pinged>]
: Space or carriage return-separated list of URLs that have been pinged. Default empty.

[--post_modified=<post_modified>]
: The date when the post was last modified. Default is the current time.

[--post_modified_gmt=<post_modified_gmt>]
: The date when the post was last modified in the GMT timezone. Default is the current time.

[--post_parent=<post_parent>]
: Set this for the post it belongs to, if any. Default 0.

[--menu_order=<menu_order>]
: The order the post should be displayed in. Default 0.

[--post_mime_type=<post_mime_type>]
: The mime type of the post. Default empty.

[--guid=<guid>]
: Global Unique ID for referencing the post. Default empty.

[--post_category=<post_category>]
: Array of category names, slugs, or IDs. Defaults to value of the \'default_category\' option.

[--tags_input=<tags_input>]
: Array of tag names, slugs, or IDs. Default empty.

[--tax_input=<tax_input>]
: Array of taxonomy terms keyed by their taxonomy name. Default empty.

[--meta_input=<meta_input>]
: Array in JSON format of post meta values keyed by their post meta key. Default empty.

[<file>]
: Read post content from <file>. If this value is present, the
    `--post_content` argument will be ignored.

  Passing `-` as the filename will cause post content to
  be read from STDIN.

--<field>=<value>
: One or more fields to update. See wp_insert_post().

[--defer-term-counting]
: Recalculate term count in batch, for a performance boost.

## EXAMPLES

    $ wp post update 123 --post_name=something --post_status=draft
    Success: Updated post 123.

    # Update a post with multiple meta values.
    $ wp post update 123 --meta_input=\'{"key1":"value1","key2":"value2"}\'
    Success: Updated post 123.

    # Update multiple posts at once.
    $ wp post update 123 456 --post_author=789
    Success: Updated post 123.
    Success: Updated post 456.

    # Update all posts of a given post type at once.
    $ wp post update $(wp post list --post_type=page --format=ids) --post_author=123
    Success: Updated post 123.
    Success: Updated post 456.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'The ID of the user who added the post. Default is the current user ID.',
                'name' => 'post_author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date of the post. Default is the current time.',
                'name' => 'post_date',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date of the post in the GMT timezone. Default is the value of $post_date.',
                'name' => 'post_date_gmt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post content. Default empty.',
                'name' => 'post_content',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The filtered post content. Default empty.',
                'name' => 'post_content_filtered',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post title. Default empty.',
                'name' => 'post_title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post excerpt. Default empty.',
                'name' => 'post_excerpt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post status. Default \'draft\'.',
                'name' => 'post_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post type. Default \'post\'.',
                'name' => 'post_type',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Whether the post can accept comments. Accepts \'open\' or \'closed\'. Default is the value of \'default_comment_status\' option.',
                'name' => 'comment_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Whether the post can accept pings. Accepts \'open\' or \'closed\'. Default is the value of \'default_ping_status\' option.',
                'name' => 'ping_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The password to access the post. Default empty.',
                'name' => 'post_password',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The post name. Default is the sanitized post title when creating a new post.',
                'name' => 'post_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Space or carriage return-separated list of URLs to ping. Default empty.',
                'name' => 'to_ping',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Space or carriage return-separated list of URLs that have been pinged. Default empty.',
                'name' => 'pinged',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date when the post was last modified. Default is the current time.',
                'name' => 'post_modified',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date when the post was last modified in the GMT timezone. Default is the current time.',
                'name' => 'post_modified_gmt',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set this for the post it belongs to, if any. Default 0.',
                'name' => 'post_parent',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The order the post should be displayed in. Default 0.',
                'name' => 'menu_order',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The mime type of the post. Default empty.',
                'name' => 'post_mime_type',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Global Unique ID for referencing the post. Default empty.',
                'name' => 'guid',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array of category names, slugs, or IDs. Defaults to value of the \'default_category\' option.',
                'name' => 'post_category',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array of tag names, slugs, or IDs. Default empty.',
                'name' => 'tags_input',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array of taxonomy terms keyed by their taxonomy name. Default empty.',
                'name' => 'tax_input',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array in JSON format of post meta values keyed by their post meta key. Default empty.',
                'name' => 'meta_input',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'file',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => false,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'name' => 'defer-term-counting',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates one or more existing posts.',
        'synopsis' => '<id>... [--post_author=<post_author>] [--post_date=<post_date>] [--post_date_gmt=<post_date_gmt>] [--post_content=<post_content>] [--post_content_filtered=<post_content_filtered>] [--post_title=<post_title>] [--post_excerpt=<post_excerpt>] [--post_status=<post_status>] [--post_type=<post_type>] [--comment_status=<comment_status>] [--ping_status=<ping_status>] [--post_password=<post_password>] [--post_name=<post_name>] [--to_ping=<to_ping>] [--pinged=<pinged>] [--post_modified=<post_modified>] [--post_modified_gmt=<post_modified_gmt>] [--post_parent=<post_parent>] [--menu_order=<menu_order>] [--post_mime_type=<post_mime_type>] [--guid=<guid>] [--post_category=<post_category>] [--tags_input=<tags_input>] [--tax_input=<tax_input>] [--meta_input=<meta_input>] [<file>] --<field>=<value> [--defer-term-counting]',
    ),
    'post url-to-id' => array(
        'command' => 'post url-to-id',
        'fields' => null,
        'longdesc' => '## OPTIONS

<url>
: The URL of the post to get.

## EXAMPLES

    # Get post ID by URL
    $ wp post url-to-id https://example.com/?p=1
    1',
        'name' => 'url-to-id',
        'params' => array(
            array(
                'name' => 'url',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets the post ID for a given URL.',
        'synopsis' => '<url>',
    ),
);
