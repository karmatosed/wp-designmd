import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { CSSProperties } from '@wordpress/element';
import type { DesignMdTokens } from '../types/designmd';

interface TokenPreviewProps {
	tokens?: DesignMdTokens;
}

function toCssProperties( styles: Record< string, string > ): CSSProperties {
	return Object.entries( styles ).reduce< CSSProperties >( ( acc, [ key, value ] ) => {
		const cssKey = key.replace( /-([a-z])/g, ( _, char: string ) => char.toUpperCase() );
		return {
			...acc,
			[ cssKey ]: value,
		};
	}, {} );
}

export default function TokenPreview( { tokens }: TokenPreviewProps ) {
	if ( ! tokens ) {
		return null;
	}

	const colors = Object.entries( tokens.colors ?? {} );
	const typography = Object.entries( tokens.typography ?? {} );
	const spacing = Object.entries( tokens.spacing ?? {} );
	const rounded = Object.entries( tokens.rounded ?? {} );

	return (
		<>
			<Card className="wp-designmd-panel">
				<CardBody>
					<h2 className="wp-designmd-panel-title">{ __( 'Colors', 'wp-designmd' ) }</h2>
					{ colors.length === 0 && (
						<p className="wp-designmd-empty">{ __( 'No color tokens available.', 'wp-designmd' ) }</p>
					) }
					{ colors.map( ( [ key, value ] ) => (
						<div key={ key } className="wp-designmd-color-row">
							<div className="wp-designmd-swatch" style={ { backgroundColor: value } } aria-hidden />
							<span className="wp-designmd-token-label">{ key }</span>
							<span className="wp-designmd-token-value">{ value }</span>
						</div>
					) ) }
				</CardBody>
			</Card>

			<Card className="wp-designmd-panel">
				<CardBody>
					<h2 className="wp-designmd-panel-title">{ __( 'Typography', 'wp-designmd' ) }</h2>
					{ typography.length === 0 && (
						<p className="wp-designmd-empty">{ __( 'No typography tokens available.', 'wp-designmd' ) }</p>
					) }
					{ typography.map( ( [ key, value ] ) => (
						<div key={ key } className="wp-designmd-type-sample">
							<code className="wp-designmd-token-label">{ key }</code>
							<p style={ toCssProperties( value ) }>
								{ __( 'The quick brown fox jumps over the lazy dog.', 'wp-designmd' ) }
							</p>
						</div>
					) ) }
				</CardBody>
			</Card>

			{ ( spacing.length > 0 || rounded.length > 0 ) && (
				<Card className="wp-designmd-panel">
					<CardBody>
						{ spacing.length > 0 && (
							<>
								<h2 className="wp-designmd-panel-title">{ __( 'Spacing', 'wp-designmd' ) }</h2>
								<dl className="wp-designmd-dl">
									{ spacing.map( ( [ key, value ] ) => (
										<div key={ key }>
											<dt>{ key }</dt>
											<dd>{ value }</dd>
										</div>
									) ) }
								</dl>
							</>
						) }
						{ rounded.length > 0 && (
							<>
								<h2 className="wp-designmd-panel-title" style={ { marginTop: spacing.length > 0 ? 16 : 0 } }>
									{ __( 'Rounded', 'wp-designmd' ) }
								</h2>
								<dl className="wp-designmd-dl">
									{ rounded.map( ( [ key, value ] ) => (
										<div key={ key }>
											<dt>{ key }</dt>
											<dd>{ value }</dd>
										</div>
									) ) }
								</dl>
							</>
						) }
					</CardBody>
				</Card>
			) }
		</>
	);
}
