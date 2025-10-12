<?php
namespace PortableInfobox\Parsoid;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Wt2Html\DOM\Processors\WrapSections;
use Wikimedia\Parsoid\Wt2Html\DOM\Processors\WrapSectionsState;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\ContentUtils;

class PortableInfoboxWrapSectionsDOMProcessor extends WrapSections {
	/**
	 * DOM Postprocessor that editwars itself by removing the sections wrapped inside of the infobox.
	 * @inheritDoc
	 */
	public function run(
		Env $env, Node $root, array $options = [], bool $atTopLevel = false
	): void {
		if ( !$env->getWrapSections() ) {
			return;
		}

		$state = new WrapSectionsState(
			$env,
			$options['frame'],
			$root
		);
		$state->run();

		$this->removeSectionsFromInfoboxes($root);
	}

	public function removeSectionsFromInfoboxes( Node $root ) {
		$node = $root->firstChild;

		while ( $node ) {
			if ( $node instanceof Element ) {
				if ( DOMUtils::hasTypeOf($node, 'mw:Extension/infobox') ) {
					ContentUtils::stripUnnecessaryWrappersAndSyntheticNodes($node);
				} else {
					$this->removeSectionsFromInfoboxes($node);
				}
			}

			$node = $node->nextSibling;
		}
	}
}