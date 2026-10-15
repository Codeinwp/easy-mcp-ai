<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'comment approve' => array(
        'command' => 'comment approve',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: The IDs of the comments to approve.

## EXAMPLES

    # Approve comment.
    $ wp comment approve 1337
    Success: Approved comment 1337.',
        'name' => 'approve',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Approves a comment.',
        'synopsis' => '<id>...',
    ),
    'comment count' => array(
        'command' => 'comment count',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<post-id>]
: The ID of the post to count comments in.

## EXAMPLES

    # Count comments on whole blog.
    $ wp comment count
    approved:        33
    spam:            3
    trash:           1
    post-trashed:    0
    all:             34
    moderated:       1
    total_comments:  37

    # Count comments in a post.
    $ wp comment count 42
    approved:        19
    spam:            0
    trash:           0
    post-trashed:    0
    all:             19
    moderated:       0
    total_comments:  19',
        'name' => 'count',
        'params' => array(
            array(
                'name' => 'post-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Counts comments, on whole blog or on a given post.',
        'synopsis' => '[<post-id>]',
    ),
    'comment create' => array(
        'command' => 'comment create',
        'fields' => null,
        'longdesc' => '## OPTIONS

[--<field>=<value>]
: Associative args for the new comment. See wp_insert_comment().

[--porcelain]
: Output just the new comment id.

## EXAMPLES

    # Create comment.
    $ wp comment create --comment_post_ID=15 --comment_content="hello blog" --comment_author="wp-cli"
    Success: Created comment 932.',
        'name' => 'create',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Creates a new comment.',
        'synopsis' => '[--<field>=<value>] [--porcelain]',
    ),
    'comment delete' => array(
        'command' => 'comment delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: One or more IDs of comments to delete.

[--force]
: Skip the trash bin.

## EXAMPLES

    # Delete comment.
    $ wp comment delete 1337 --force
    Success: Deleted comment 1337.

    # Delete multiple comments.
    $ wp comment delete 1337 2341 --force
    Success: Deleted comment 1337.
    Success: Deleted comment 2341.',
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
        ),
        'shortdesc' => 'Deletes a comment.',
        'synopsis' => '<id>... [--force]',
    ),
    'comment exists' => array(
        'command' => 'comment exists',
        'fields' => null,
        'longdesc' => 'Displays a success message if the comment does exist.

## OPTIONS

<id>
: The ID of the comment to check.

## EXAMPLES

    # Check whether comment exists.
    $ wp comment exists 1337
    Success: Comment with ID 1337 exists.',
        'name' => 'exists',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Verifies whether a comment exists.',
        'synopsis' => '<id>',
    ),
    'comment generate' => array(
        'command' => 'comment generate',
        'fields' => null,
        'longdesc' => 'Creates a specified number of new comments with dummy data.

## OPTIONS

[--count=<number>]
: How many comments to generate?
---
default: 100
---

[--post_id=<post-id>]
: Assign comments to a specific post.

[--format=<format>]
: Render output in a particular format.
---
default: progress
options:
  - progress
  - ids
---

## EXAMPLES

    # Generate comments for the given post.
    $ wp comment generate --format=ids --count=3 --post_id=123
    138 139 140

    # Add meta to every generated comment.
    $ wp comment generate --format=ids --count=3 | xargs -d \' \' -I % wp comment meta add % foo bar
    Success: Added custom field.
    Success: Added custom field.
    Success: Added custom field.',
        'name' => 'generate',
        'params' => array(
            array(
                'default' => 100,
                'desc' => 'How many comments to generate?',
                'name' => 'count',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Assign comments to a specific post.',
                'name' => 'post_id',
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
        'shortdesc' => 'Generates some number of new dummy comments.',
        'synopsis' => '[--count=<number>] [--post_id=<post-id>] [--format=<format>]',
    ),
    'comment get' => array(
        'command' => 'comment get',
        'fields' => array(
            'default' => array(
                'comment_ID',
                'comment_post_ID',
                'comment_author',
                'comment_author_email',
                'comment_author_url',
                'comment_author_IP',
                'comment_date',
                'comment_date_gmt',
                'comment_content',
                'comment_karma',
                'comment_approved',
                'comment_agent',
                'comment_type',
                'comment_parent',
                'user_id',
                'url',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<id>
: The comment to get.

[--field=<field>]
: Instead of returning the whole comment, returns the value of a single field.

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

    # Get comment.
    $ wp comment get 21 --field=content
    Thanks for all the comments, everyone!',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole comment, returns the value of a single field.',
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
        'shortdesc' => 'Gets the data of a single comment.',
        'synopsis' => '<id> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'comment list' => array(
        'command' => 'comment list',
        'fields' => array(
            'default' => array(
                'comment_ID',
                'comment_post_ID',
                'comment_date',
                'comment_approved',
                'comment_author',
                'comment_author_email',
            ),
            'optional' => array(
                'comment_author_url',
                'comment_author_IP',
                'comment_date_gmt',
                'comment_content',
                'comment_karma',
                'comment_agent',
                'comment_type',
                'comment_parent',
                'user_id',
                'url',
            ),
        ),
        'longdesc' => 'Display comments based on all arguments supported by
[WP_Comment_Query()](https://developer.wordpress.org/reference/classes/WP_Comment_Query/__construct/).

## OPTIONS

[--<field>=<value>]
: One or more args to pass to WP_Comment_Query.

[--field=<field>]
: Prints the value of a single field for each comment.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - ids
  - csv
  - json
  - count
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each comment:

* comment_ID
* comment_post_ID
* comment_date
* comment_approved
* comment_author
* comment_author_email

These fields are optionally available:

* comment_author_url
* comment_author_IP
* comment_date_gmt
* comment_content
* comment_karma
* comment_agent
* comment_type
* comment_parent
* user_id
* url

## EXAMPLES

    # List comment IDs.
    $ wp comment list --field=ID
    22
    23
    24

    # List comments of a post.
    $ wp comment list --post_id=1 --fields=ID,comment_date,comment_author
    +------------+---------------------+----------------+
    | comment_ID | comment_date        | comment_author |
    +------------+---------------------+----------------+
    | 1          | 2015-06-20 09:00:10 | Mr WordPress   |
    +------------+---------------------+----------------+

    # List approved comments.
    $ wp comment list --number=3 --status=approve --fields=ID,comment_date,comment_author
    +------------+---------------------+----------------+
    | comment_ID | comment_date        | comment_author |
    +------------+---------------------+----------------+
    | 1          | 2015-06-20 09:00:10 | Mr WordPress   |
    | 30         | 2013-03-14 12:35:07 | John Doe       |
    | 29         | 2013-03-14 11:56:08 | Jane Doe       |
    +------------+---------------------+----------------+

    # List unapproved comments.
    $ wp comment list --number=3 --status=hold --fields=ID,comment_date,comment_author
    +------------+---------------------+----------------+
    | comment_ID | comment_date        | comment_author |
    +------------+---------------------+----------------+
    | 8          | 2023-11-10 13:13:06 | John Doe       |
    | 7          | 2023-11-10 13:09:55 | Mr WordPress   |
    | 9          | 2023-11-10 11:22:31 | Jane Doe       |
    +------------+---------------------+----------------+

    # List comments marked as spam.
    $ wp comment list --status=spam --fields=ID,comment_date,comment_author
    +------------+---------------------+----------------+
    | comment_ID | comment_date        | comment_author |
    +------------+---------------------+----------------+
    | 2          | 2023-11-10 11:22:31 | Jane Doe       |
    +------------+---------------------+----------------+

    # List comments in trash.
    $ wp comment list --status=trash --fields=ID,comment_date,comment_author
    +------------+---------------------+----------------+
    | comment_ID | comment_date        | comment_author |
    +------------+---------------------+----------------+
    | 3          | 2023-11-10 11:22:31 | John Doe       |
    +------------+---------------------+----------------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each comment.',
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
                    'ids',
                    'csv',
                    'json',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a list of comments.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'comment meta add' => array(
        'command' => 'comment meta add',
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
    'comment meta delete' => array(
        'command' => 'comment meta delete',
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
    'comment meta get' => array(
        'command' => 'comment meta get',
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
    'comment meta list' => array(
        'command' => 'comment meta list',
        'fields' => array(
            'default' => array(
                'comment_id',
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
    'comment meta patch' => array(
        'command' => 'comment meta patch',
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
    'comment meta pluck' => array(
        'command' => 'comment meta pluck',
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
    'comment meta update' => array(
        'alias' => 'set',
        'command' => 'comment meta update',
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
    'comment recount' => array(
        'command' => 'comment recount',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: IDs for one or more posts to update.

## EXAMPLES

    # Recount comment for the post.
    $ wp comment recount 123
    Updated post 123 comment count to 67.',
        'name' => 'recount',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Recalculates the comment_count value for one or more posts.',
        'synopsis' => '<id>...',
    ),
    'comment spam' => array(
        'command' => 'comment spam',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: The IDs of the comments to mark as spam.

## EXAMPLES

    # Spam comment.
    $ wp comment spam 1337
    Success: Marked as spam comment 1337.',
        'name' => 'spam',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Marks a comment as spam.',
        'synopsis' => '<id>...',
    ),
    'comment status' => array(
        'command' => 'comment status',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the comment to check.

## EXAMPLES

    # Get status of comment.
    $ wp comment status 1337
    approved',
        'name' => 'status',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets the status of a comment.',
        'synopsis' => '<id>',
    ),
    'comment trash' => array(
        'command' => 'comment trash',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: The IDs of the comments to trash.

## EXAMPLES

    # Trash comment.
    $ wp comment trash 1337
    Success: Trashed comment 1337.',
        'name' => 'trash',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Trashes a comment.',
        'synopsis' => '<id>...',
    ),
    'comment unapprove' => array(
        'command' => 'comment unapprove',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: The IDs of the comments to unapprove.

## EXAMPLES

    # Unapprove comment.
    $ wp comment unapprove 1337
    Success: Unapproved comment 1337.',
        'name' => 'unapprove',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Unapproves a comment.',
        'synopsis' => '<id>...',
    ),
    'comment unspam' => array(
        'command' => 'comment unspam',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: The IDs of the comments to unmark as spam.

## EXAMPLES

    # Unspam comment.
    $ wp comment unspam 1337
    Success: Unspammed comment 1337.',
        'name' => 'unspam',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Unmarks a comment as spam.',
        'synopsis' => '<id>...',
    ),
    'comment untrash' => array(
        'command' => 'comment untrash',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: The IDs of the comments to untrash.

## EXAMPLES

    # Untrash comment.
    $ wp comment untrash 1337
    Success: Untrashed comment 1337.',
        'name' => 'untrash',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Untrashes a comment.',
        'synopsis' => '<id>...',
    ),
    'comment update' => array(
        'command' => 'comment update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>...
: One or more IDs of comments to update.

--<field>=<value>
: One or more fields to update. See wp_update_comment().

## EXAMPLES

    # Update comment.
    $ wp comment update 123 --comment_author=\'That Guy\'
    Success: Updated comment 123.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => false,
                'repeating' => false,
                'type' => 'generic',
            ),
        ),
        'shortdesc' => 'Updates one or more comments.',
        'synopsis' => '<id>... --<field>=<value>',
    ),
);
