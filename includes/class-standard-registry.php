<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Standard_Registry {

    private static $cache = [];

    /**
     * Returns array of available standards: [ id => [ name_it, name_en ] ]
     */
    public static function get_available_standards() {
        if ( ! empty( self::$cache['list'] ) ) {
            return self::$cache['list'];
        }

        $data_dir  = GAPNEXT_WP_DIR . 'includes/data/';
        $standards = [];

        foreach ( glob( $data_dir . '*.php' ) as $file ) {
            $standard = require $file;
            if ( isset( $standard['id'] ) ) {
                $standards[ $standard['id'] ] = [
                    'name_it' => $standard['name_it'],
                    'name_en' => $standard['name_en'],
                ];
            }
        }

        self::$cache['list'] = $standards;
        return $standards;
    }

    /**
     * Returns full standard data for a given ID and language.
     * Filters clause fields to the requested language.
     *
     * @param string $standard_id
     * @param string $lang 'it' or 'en'
     * @return array|null
     */
    public static function get_standard_data( $standard_id, $lang = 'it' ) {
        $lang      = in_array( $lang, [ 'it', 'en' ], true ) ? $lang : 'it';
        $cache_key = $standard_id . '_' . $lang;

        if ( isset( self::$cache[ $cache_key ] ) ) {
            return self::$cache[ $cache_key ];
        }

        $file = GAPNEXT_WP_DIR . 'includes/data/' . sanitize_file_name( $standard_id ) . '.php';
        if ( ! file_exists( $file ) ) return null;

        $raw = require $file;

        // Normalize clauses to language-specific fields
        $clauses = array_map( function( $c ) use ( $lang ) {
            return [
                'reference'   => $c['reference'],
                'clause_ref'  => $c['clause_ref'] ?? $c['reference'],
                'section'     => $c[ 'section_' . $lang ] ?? $c['section_it'] ?? '',
                'title'       => $c[ 'title_' . $lang ] ?? $c['title_it'],
                'description' => $c[ 'description_' . $lang ] ?? $c['description_it'] ?? '',
                'help_text'   => $c[ 'help_text_' . $lang ] ?? $c['help_text_it'] ?? '',
                'level'       => (int) ( $c['level'] ?? 2 ),
            ];
        }, $raw['clauses'] );

        $result = [
            'id'      => $raw['id'],
            'name'    => $raw[ 'name_' . $lang ] ?? $raw['name_it'],
            'name_it' => $raw['name_it'],
            'name_en' => $raw['name_en'],
            'clauses' => $clauses,
        ];

        self::$cache[ $cache_key ] = $result;
        return $result;
    }
}
