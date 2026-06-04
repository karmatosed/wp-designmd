import { Notice, Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { LintFinding } from '../types/designmd';

interface LintReportProps {
	findings?: LintFinding[];
}

export default function LintReport( { findings = [] }: LintReportProps ) {
	return (
		<Card className="wp-designmd-panel">
			<CardBody>
				<h2 className="wp-designmd-panel-title">{ __( 'Lint report', 'wp-designmd' ) }</h2>
				{ findings.length === 0 && (
					<p className="wp-designmd-empty">{ __( 'No lint findings.', 'wp-designmd' ) }</p>
				) }
				{ findings.map( ( finding, index ) => (
					<Notice
						key={ `${ finding.path }-${ index }` }
						status={ finding.severity === 'error' ? 'error' : finding.severity }
						isDismissible={ false }
					>
						<strong>{ finding.path }</strong>: { finding.message }
					</Notice>
				) ) }
			</CardBody>
		</Card>
	);
}
