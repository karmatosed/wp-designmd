import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface ProseViewerProps {
	prose?: Record< string, string >;
}

export default function ProseViewer( { prose }: ProseViewerProps ) {
	const sections = Object.entries( prose ?? {} );

	if ( sections.length === 0 ) {
		return null;
	}

	return (
		<>
			{ sections.map( ( [ section, content ] ) => (
				<Card key={ section } className="wp-designmd-panel">
					<CardBody>
						<h2 className="wp-designmd-panel-title">{ section }</h2>
						<div className="wp-designmd-prose-content">{ content }</div>
					</CardBody>
				</Card>
			) ) }
		</>
	);
}
