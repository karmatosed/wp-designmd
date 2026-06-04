import { Card, CardBody, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ActionsPanel from './components/ActionsPanel';
import FeaturePagesPicker from './components/FeaturePagesPicker';
import LintReport from './components/LintReport';
import ProseViewer from './components/ProseViewer';
import TokenPreview from './components/TokenPreview';
import { useDesignMd } from './hooks/useDesignMd';

export default function App() {
	const { data, isLoading, error, generate, regenerate, fillGaps, download } = useDesignMd();

	return (
		<div className="wp-designmd-admin-page">
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }

			{ data?.notices?.map( ( notice, index ) => (
				<Notice key={ `${ notice.type }-${ index }` } status={ notice.type } isDismissible={ false }>
					{ notice.message }
				</Notice>
			) ) }

			<div className="wp-designmd-layout">
				<aside className="wp-designmd-sidebar">
					<ActionsPanel
						isLoading={ isLoading }
						canFillGaps={ data?.meta?.sparse === true || data?.sparse === true }
						generatedAt={ data?.meta?.generated_at }
						themeSlug={ data?.meta?.theme_slug }
						onGenerate={ () => void generate() }
						onRegenerate={ () => void regenerate() }
						onFillGaps={ () => void fillGaps() }
						onDownload={ () => void download() }
					/>
					<FeaturePagesPicker />
					<LintReport findings={ data?.lint?.findings ?? [] } />
				</aside>

				<main className="wp-designmd-main">
					{ ! data?.tokens && ! isLoading && (
						<Card className="wp-designmd-panel">
							<CardBody>
								<p className="wp-designmd-empty">
									{ __( 'No DESIGN.md yet. Click Generate to create one from your block theme.', 'wp-designmd' ) }
								</p>
							</CardBody>
						</Card>
					) }
					<TokenPreview tokens={ data?.tokens } />
					<ProseViewer prose={ data?.prose } />
				</main>
			</div>
		</div>
	);
}
