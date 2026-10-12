import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

// Saved output is null: the block is rendered on the server (PHP) so rates
// stay fresh and cached, and the markup is never stored in the post.
registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
