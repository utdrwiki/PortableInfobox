<?php

namespace PortableInfobox\Parsoid;

use ReflectionObject;
use Wikimedia\Parsoid\Core\ContentMetadataCollectorStringSets as CMCSS;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class InfoboxTag extends ExtensionTagHandler implements ExtensionModule {

	/**
	 * @inheritDoc
	 */
	public function getConfig(): array {
		return [
			'name' => 'PortableInfobox',
			'tags' => [
				[
					'name' => 'infobox',
					'handler' => self::class,
				],
			],
			'domProcessors' => [
				'PortableInfobox\\Parsoid\\PortableInfoboxDOMProcessor',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function sourceToDom( ParsoidExtensionAPI $api, string $src, array $args ) {
		self::injectCustomSectionProcessor($api);

		$domFragments = $api->extTagToDOM( $args, $src, [
			'wrapperTag' => 'aside',
			'parseOpts' => [
				'extTag' => 'infobox',
				'context' => 'inline',
			],
		] );

		$api->getMetadata()->appendOutputStrings( CMCSS::MODULE_STYLE, [ 'ext.PortableInfobox.styles' ] );
		$api->getMetadata()->appendOutputStrings( CMCSS::MODULE, [ 'ext.PortableInfobox.scripts' ] );

		// return this back. At this point, we have constructed the outer tag (<aside class=...</aside>)
		// and this function is done with its work. The rest of the work will happen in the DOMProcessor
		return $domFragments;
	}

	/**
	 * Nothing to see here.
	 * @param \Wikimedia\Parsoid\Ext\ParsoidExtensionAPI $extApi
	 * @return void
	 */
	private static function injectCustomSectionProcessor(ParsoidExtensionAPI $extApi) {
		$parserPipeline = null;

		// Find the parser pipeline in an acceptable and maintainable manner
		foreach ( debug_backtrace() as $backtraceEntry ) {
			if ( array_key_exists('object', $backtraceEntry) && get_class($backtraceEntry['object']) == 'Wikimedia\Parsoid\Wt2Html\ParserPipeline' ) {
				$parserPipeline = $backtraceEntry['object'];
			}
		}
		
		if ( !$parserPipeline ) {
			$extApi->log('fatal/PI', 'Could not find parser pipeline in call stack.');
			return;
		}

		$parserPipelineRO = new ReflectionObject($parserPipeline);
		$stagesProp = $parserPipelineRO->getProperty('stages');
		$pipelineStages = $stagesProp->getValue($parserPipeline);

		$DOMProcessorPipeline = null;
		foreach ( $pipelineStages as $pipelineStage ) {
			if ( get_class($pipelineStage) == 'Wikimedia\Parsoid\Wt2Html\DOMProcessorPipeline' ) {
				$DOMProcessorPipeline = $pipelineStage;
			}
		}

		if ( !$DOMProcessorPipeline ) {
			$extApi->log('fatal/PI', 'Could not find DOM Processor Pipeline in the parser pipeline.');
			return;
		}

		$DOMProcessorPipelineRO = new ReflectionObject($DOMProcessorPipeline);
		$processorsProp = $DOMProcessorPipelineRO->getProperty('processors');
		$processors = $processorsProp->getValue($DOMProcessorPipeline);

		// Find the existing processor we have to override.
		$sectionsProcessorIndex = null;
		foreach ( $processors as $processorIndex=>$processor ) {
			if ( $processor['name'] == 'WrapSections' ) {
				$sectionsProcessorIndex = $processorIndex;
			}
		}

		if ( !$sectionsProcessorIndex ) {
			$extApi->log('fatal/PI', 'Could not find WrapSections processor for replacement with custom implementation.');
			return;
		}

		$extApi->log('debug/PI', 'Overriding the processor at index ' . $sectionsProcessorIndex);

		$processors[$sectionsProcessorIndex]['proc'] = new PortableInfoboxWrapSectionsDOMProcessor();

		$processorsProp->setValue($DOMProcessorPipeline, $processors);
	}
}
