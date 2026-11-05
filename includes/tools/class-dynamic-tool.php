<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dynamic_Tool extends Base_Tool {

    private $name;
    private $description;
    private $category;
    private $capability;
    private $input_schema;
    private $output_schema;
    private $executor;
    private $annotations_override;
    private $task_contract;
    private $accepted_arguments;
    private $ability;

    private $refusal;

    public function __construct( array $config ) {
        $this->validate_required( $config, array( 'name', 'description', 'executor' ) );

        $this->name         = $config['name'];
        $this->description  = $config['description'];
        $this->executor     = $config['executor'];
        $this->category     = isset( $config['category'] )    ? $config['category']    : 'general';
        $this->capability   = isset( $config['capability'] )  ? $config['capability']  : 'manage_options';
        $this->input_schema = isset( $config['input_schema'] ) ? $config['input_schema'] : array(
            'type'       => 'object',
            'properties' => new \stdClass(),
        );
        $this->output_schema = isset( $config['output_schema'] ) ? $config['output_schema'] : null;
        $this->annotations_override = isset( $config['annotations'] ) ? $config['annotations'] : null;
        $this->task_contract = isset( $config['task'] ) && is_array( $config['task'] ) ? $config['task'] : null;
        $this->refusal       = isset( $config['refusal'] ) ? $config['refusal'] : null;
        $this->accepted_arguments = isset( $config['accepted_arguments'] ) && is_array( $config['accepted_arguments'] )
            ? array_values( array_map( 'strval', $config['accepted_arguments'] ) )
            : null;
        $this->ability = isset( $config['ability'] ) && is_string( $config['ability'] ) ? $config['ability'] : null;
    }

    public function get_name()                 { return $this->name; }
    public function get_description()          { return $this->description; }
    public function get_category()             { return $this->category; }
    public function get_required_capability()  { return $this->capability; }
    public function get_input_schema()         { return $this->input_schema; }
    public function get_output_schema()        { return $this->output_schema; }
    public function get_task_contract()        { return $this->task_contract; }

    public function accepted_arguments(): ?array {
        return $this->accepted_arguments;
    }

    public function refuse_undeclared_arguments( array $arguments ): void {
        $undeclared = $this->undeclared_arguments( $arguments );
        if ( array() !== $undeclared && null !== $this->ability ) {
            $violation = Ability_Input_Policy::callback_violation( $this->ability, array_intersect_key( $arguments, array_flip( $undeclared ) ) );
            if ( null !== $violation ) {
                throw new \InvalidArgumentException( $violation ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        parent::refuse_undeclared_arguments( $arguments );
    }

    public function get_annotations() {
        if ( null !== $this->annotations_override ) {
            return $this->annotations_override;
        }
        return parent::get_annotations();
    }

    public function get_definition() {
        $definition = parent::get_definition();
        unset( $definition['outputSchema'] );
        return $definition;
    }

    public function execute( array $arguments ) {
        return call_user_func( $this->executor, $arguments );
    }

    public function refusal( array $arguments ): ?string {
        return null === $this->refusal ? null : call_user_func( $this->refusal, $arguments );
    }
}
