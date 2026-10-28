<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'term create' => array(
        'command' => 'term create',
        'fields' => null,
        'longdesc' => '## OPTIONS

<taxonomy>
: Taxonomy for the new term.

<term>
: A name for the new term.

[--slug=<slug>]
: A unique slug for the new term. Defaults to sanitized version of name.

[--description=<description>]
: A description for the new term.

[--parent=<term-id>]
: A parent for the new term.

[--porcelain]
: Output just the new term id.

## EXAMPLES

    # Create a new category "Apple" with a description.
    $ wp term create category Apple --description="A type of fruit"
    Success: Created category 199.',
        'name' => 'create',
        'params' => array(
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'A unique slug for the new term. Defaults to sanitized version of name.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A description for the new term.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A parent for the new term.',
                'name' => 'parent',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Creates a new term.',
        'synopsis' => '<taxonomy> <term> [--slug=<slug>] [--description=<description>] [--parent=<term-id>] [--porcelain]',
    ),
    'term delete' => array(
        'command' => 'term delete',
        'fields' => null,
        'longdesc' => 'Errors if the term doesn\'t exist, or there was a problem in deleting it.

## OPTIONS

<taxonomy>
: Taxonomy of the term to delete.

<term>...
: One or more IDs or slugs of terms to delete.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: id
options:
  - slug
  - id
---

## EXAMPLES

    # Delete post category by id
    $ wp term delete category 15
    Deleted category 15.
    Success: Deleted 1 of 1 terms.

    # Delete post category by slug
    $ wp term delete category apple --by=slug
    Deleted category 15.
    Success: Deleted 1 of 1 terms.

    # Delete all post tags
    $ wp term list post_tag --field=term_id | xargs wp term delete post_tag
    Deleted post_tag 159.
    Deleted post_tag 160.
    Deleted post_tag 161.
    Success: Deleted 3 of 3 terms.',
        'name' => 'delete',
        'params' => array(
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
                'default' => 'id',
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
        'shortdesc' => 'Deletes an existing term.',
        'synopsis' => '<taxonomy> <term>... [--by=<field>]',
    ),
    'term generate' => array(
        'command' => 'term generate',
        'fields' => null,
        'longdesc' => 'Creates a specified number of new terms with dummy data.

## OPTIONS

<taxonomy>
: The taxonomy for the generated terms.

[--count=<number>]
: How many terms to generate?
---
default: 100
---

[--max_depth=<number>]
: Generate child terms down to a certain depth.
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

    # Generate post categories.
    $ wp term generate category --count=10
    Generating terms  100% [=========] 0:02 / 0:02

    # Add meta to every generated term.
    $ wp term generate category --format=ids --count=3 | xargs -d \' \' -I % wp term meta add % foo bar
    Success: Added custom field.
    Success: Added custom field.
    Success: Added custom field.',
        'name' => 'generate',
        'params' => array(
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 100,
                'desc' => 'How many terms to generate?',
                'name' => 'count',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 1,
                'desc' => 'Generate child terms down to a certain depth.',
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
        'shortdesc' => 'Generates some terms.',
        'synopsis' => '<taxonomy> [--count=<number>] [--max_depth=<number>] [--format=<format>]',
    ),
    'term get' => array(
        'command' => 'term get',
        'fields' => array(
            'default' => array(
                'term_id',
                'name',
                'slug',
                'term_group',
                'term_taxonomy_id',
                'taxonomy',
                'description',
                'parent',
                'count',
                'filter',
                'url',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<taxonomy>
: Taxonomy of the term to get

<term>
: ID or slug of the term to get

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: id
options:
  - slug
  - id
---

[--field=<field>]
: Instead of returning the whole term, returns the value of a single field.

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

    # Get details about a category with id 199.
    $ wp term get category 199 --format=json
    {"term_id":199,"name":"Apple","slug":"apple","term_group":0,"term_taxonomy_id":199,"taxonomy":"category","description":"A type of fruit","parent":0,"count":0,"filter":"raw"}

    # Get details about a category with slug apple.
    $ wp term get category apple --by=slug --format=json
    {"term_id":199,"name":"Apple","slug":"apple","term_group":0,"term_taxonomy_id":199,"taxonomy":"category","description":"A type of fruit","parent":0,"count":0,"filter":"raw"}',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'id',
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
                'desc' => 'Instead of returning the whole term, returns the value of a single field.',
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
        'shortdesc' => 'Gets details about a term.',
        'synopsis' => '<taxonomy> <term> [--by=<field>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'term list' => array(
        'command' => 'term list',
        'fields' => array(
            'default' => array(
                'term_id',
                'term_taxonomy_id',
                'name',
                'slug',
                'description',
                'parent',
                'count',
            ),
            'optional' => array(
                'url',
            ),
        ),
        'longdesc' => '## OPTIONS

<taxonomy>...
: List terms of one or more taxonomies

[--<field>=<value>]
: Filter by one or more fields (see get_terms() $args parameter for a list of fields).

[--field=<field>]
: Prints the value of a single field for each term.

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

These fields will be displayed by default for each term:

* term_id
* term_taxonomy_id
* name
* slug
* description
* parent
* count

These fields are optionally available:

* url

## EXAMPLES

    # List post categories
    $ wp term list category --format=csv
    term_id,term_taxonomy_id,name,slug,description,parent,count
    2,2,aciform,aciform,,0,1
    3,3,antiquarianism,antiquarianism,,0,1
    4,4,arrangement,arrangement,,0,1
    5,5,asmodeus,asmodeus,,0,1

    # List post tags
    $ wp term list post_tag --fields=name,slug
    +-----------+-------------+
    | name      | slug        |
    +-----------+-------------+
    | 8BIT      | 8bit        |
    | alignment | alignment-2 |
    | Articles  | articles    |
    | aside     | aside       |
    +-----------+-------------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
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
        'shortdesc' => 'Lists terms in a taxonomy.',
        'synopsis' => '<taxonomy>... [--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'term meta add' => array(
        'command' => 'term meta add',
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
    'term meta delete' => array(
        'command' => 'term meta delete',
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
    'term meta get' => array(
        'command' => 'term meta get',
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
    'term meta list' => array(
        'command' => 'term meta list',
        'fields' => array(
            'default' => array(
                'term_id',
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
    'term meta patch' => array(
        'command' => 'term meta patch',
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
    'term meta pluck' => array(
        'command' => 'term meta pluck',
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
    'term meta update' => array(
        'alias' => 'set',
        'command' => 'term meta update',
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
    'term migrate' => array(
        'command' => 'term migrate',
        'fields' => null,
        'longdesc' => '## OPTIONS

<term>
: Slug or ID of the term to migrate.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: id
options:
  - slug
  - id
---

[--from=<taxonomy>]
: Taxonomy slug of the term to migrate.

[--to=<taxonomy>]
: Taxonomy slug to migrate to.

## EXAMPLES

    # Migrate a category\'s term (video) to tag taxonomy.
    $ wp term migrate 9190 --from=category --to=post_tag
    Term \'video\' assigned to post 1155.
    Term \'video\' migrated.
    Old instance of term \'video\' removed from its original taxonomy.
    Success: Migrated the term \'video\' from taxonomy \'category\' to taxonomy \'post_tag\' for 1 post.',
        'name' => 'migrate',
        'params' => array(
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'id',
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
                'desc' => 'Taxonomy slug of the term to migrate.',
                'name' => 'from',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Taxonomy slug to migrate to.',
                'name' => 'to',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Migrate a term of a taxonomy to another taxonomy.',
        'synopsis' => '<term> [--by=<field>] [--from=<taxonomy>] [--to=<taxonomy>]',
    ),
    'term recount' => array(
        'command' => 'term recount',
        'fields' => null,
        'longdesc' => 'In instances where manual updates are made to the terms assigned to
posts in the database, the number of posts associated with a term
can become out-of-sync with the actual number of posts.

This command runs wp_update_term_count() on the taxonomy\'s terms
to bring the count back to the correct value.

## OPTIONS

<taxonomy>...
: One or more taxonomies to recalculate.

## EXAMPLES

    # Recount posts assigned to each categories and tags
    $ wp term recount category post_tag
    Success: Updated category term count.
    Success: Updated post_tag term count.

    # Recount all listed taxonomies
    $ wp taxonomy list --field=name | xargs wp term recount
    Success: Updated category term count.
    Success: Updated post_tag term count.
    Success: Updated nav_menu term count.
    Success: Updated link_category term count.
    Success: Updated post_format term count.',
        'name' => 'recount',
        'params' => array(
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Recalculates number of posts assigned to each term.',
        'synopsis' => '<taxonomy>...',
    ),
    'term update' => array(
        'command' => 'term update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<taxonomy>
: Taxonomy of the term to update.

<term>
: ID or slug for the term to update.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: id
options:
  - slug
  - id
---

[--name=<name>]
: A new name for the term.

[--slug=<slug>]
: A new slug for the term.

[--description=<description>]
: A new description for the term.

[--parent=<term-id>]
: A new parent for the term.

## EXAMPLES

    # Change category with id 15 to use the name "Apple"
    $ wp term update category 15 --name=Apple
    Success: Term updated.

    # Change category with slug apple to use the name "Apple"
    $ wp term update category apple --by=slug --name=Apple
    Success: Term updated.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'id',
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
                'desc' => 'A new name for the term.',
                'name' => 'name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A new slug for the term.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A new description for the term.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A new parent for the term.',
                'name' => 'parent',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Updates an existing term.',
        'synopsis' => '<taxonomy> <term> [--by=<field>] [--name=<name>] [--slug=<slug>] [--description=<description>] [--parent=<term-id>]',
    ),
);
