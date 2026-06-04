import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardBody, FormTokenField, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface FeatureSettingsResponse {
	feature_page_ids: number[];
	use_ai_prose?: boolean;
}

interface WpPageResponse {
	id: number;
	title?: {
		rendered?: string;
	};
}

const SETTINGS_ENDPOINT = '/wp-designmd/v1/settings';

function pageToToken( page: WpPageResponse ): string {
	const title = page.title?.rendered?.trim() || __( 'Untitled', 'wp-designmd' );
	return `${ title } (#${ page.id })`;
}

function tokenToPageId( token: string ): number | null {
	const matches = token.match( /#(\d+)\)$/ );
	if ( ! matches ) {
		return null;
	}

	return Number.parseInt( matches[ 1 ], 10 );
}

function getErrorMessage( error: unknown ): string {
	if ( error instanceof Error && error.message ) {
		return error.message;
	}

	if ( typeof error === 'object' && error !== null && 'message' in error ) {
		const errorWithMessage = error as { message?: string };
		if ( errorWithMessage.message ) {
			return errorWithMessage.message;
		}
	}

	return __( 'An unexpected error occurred.', 'wp-designmd' );
}

export default function FeaturePagesPicker() {
	const [ tokens, setTokens ] = useState< string[] >( [] );
	const [ suggestions, setSuggestions ] = useState< string[] >( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ success, setSuccess ] = useState< string | null >( null );

	const selectedPageIds = useMemo(
		() =>
			tokens
				.map( tokenToPageId )
				.filter( ( id ): id is number => id !== null && Number.isInteger( id ) && id > 0 ),
		[ tokens ]
	);

	const loadSelectedPages = useCallback( async ( ids: number[] ) => {
		if ( ids.length === 0 ) {
			setTokens( [] );
			return;
		}

		const pages = await apiFetch< WpPageResponse[] >( {
			path: `/wp/v2/pages?include=${ ids.join( ',' ) }&per_page=${ ids.length }`,
		} );
		setTokens( pages.map( pageToToken ) );
	}, [] );

	const loadSettings = useCallback( async () => {
		setIsLoading( true );
		setError( null );

		try {
			const settings = await apiFetch< FeatureSettingsResponse >( {
				path: SETTINGS_ENDPOINT,
			} );
			await loadSelectedPages( settings.feature_page_ids ?? [] );
		} catch ( requestError: unknown ) {
			setError( getErrorMessage( requestError ) );
		} finally {
			setIsLoading( false );
		}
	}, [ loadSelectedPages ] );

	const onSearch = useCallback( async ( value: string ) => {
		if ( value.trim().length < 2 ) {
			setSuggestions( [] );
			return;
		}

		try {
			const pages = await apiFetch< WpPageResponse[] >( {
				path: `/wp/v2/pages?search=${ encodeURIComponent( value ) }&per_page=20`,
			} );
			setSuggestions( pages.map( pageToToken ) );
		} catch {
			setSuggestions( [] );
		}
	}, [] );

	const save = useCallback( async () => {
		setIsSaving( true );
		setError( null );
		setSuccess( null );

		try {
			await apiFetch< FeatureSettingsResponse >( {
				path: SETTINGS_ENDPOINT,
				method: 'POST',
				data: {
					feature_page_ids: selectedPageIds,
				},
			} );
			setSuccess( __( 'Feature pages saved.', 'wp-designmd' ) );
		} catch ( requestError: unknown ) {
			setError( getErrorMessage( requestError ) );
		} finally {
			setIsSaving( false );
		}
	}, [ selectedPageIds ] );

	useEffect( () => {
		void loadSettings();
	}, [ loadSettings ] );

	return (
		<Card className="wp-designmd-panel">
			<CardBody>
				<h2 className="wp-designmd-panel-title">{ __( 'Feature pages', 'wp-designmd' ) }</h2>
				<p className="wp-designmd-meta">
					{ __( 'Always include these pages in the front-end sample scan.', 'wp-designmd' ) }
				</p>
				{ isLoading && <Spinner /> }
				<FormTokenField
					label={ __( 'Pages', 'wp-designmd' ) }
					value={ tokens }
					suggestions={ suggestions }
					onChange={ setTokens }
					onInputChange={ onSearch }
					placeholder={ __( 'Search pages…', 'wp-designmd' ) }
					disabled={ isLoading || isSaving }
				/>
				<div className="wp-designmd-save-row">
					<Button variant="secondary" onClick={ save } disabled={ isLoading || isSaving }>
						{ __( 'Save pages', 'wp-designmd' ) }
					</Button>
				</div>
				{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
				{ success && <Notice status="success" isDismissible={ false }>{ success }</Notice> }
			</CardBody>
		</Card>
	);
}
