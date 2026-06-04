import { Button, Card, CardBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface ActionsPanelProps {
	isLoading: boolean;
	canFillGaps: boolean;
	generatedAt?: string;
	themeSlug?: string;
	onGenerate: () => void;
	onRegenerate: () => void;
	onFillGaps: () => void;
	onDownload: () => void;
}

export default function ActionsPanel( {
	isLoading,
	canFillGaps,
	generatedAt,
	themeSlug,
	onGenerate,
	onRegenerate,
	onFillGaps,
	onDownload,
}: ActionsPanelProps ) {
	return (
		<Card className="wp-designmd-panel">
			<CardBody>
				<h2 className="wp-designmd-panel-title">{ __( 'Actions', 'wp-designmd' ) }</h2>
				<div className="wp-designmd-actions">
					<Button variant="primary" onClick={ onGenerate } disabled={ isLoading }>
						{ __( 'Generate', 'wp-designmd' ) }
					</Button>
					<Button variant="secondary" onClick={ onRegenerate } disabled={ isLoading }>
						{ __( 'Regenerate', 'wp-designmd' ) }
					</Button>
					{ canFillGaps && (
						<Button variant="secondary" onClick={ onFillGaps } disabled={ isLoading }>
							{ __( 'Fill gaps', 'wp-designmd' ) }
						</Button>
					) }
					<Button variant="secondary" onClick={ onDownload } disabled={ isLoading }>
						{ __( 'Download', 'wp-designmd' ) }
					</Button>
				</div>
				{ isLoading && <Spinner /> }
				{ generatedAt && (
					<p className="wp-designmd-meta">
						{ __( 'Last generated:', 'wp-designmd' ) }{ ' ' }
						{ new Date( generatedAt ).toLocaleString() }
						{ themeSlug && (
							<>
								<br />
								{ __( 'Theme:', 'wp-designmd' ) } { themeSlug }
							</>
						) }
					</p>
				) }
			</CardBody>
		</Card>
	);
}
