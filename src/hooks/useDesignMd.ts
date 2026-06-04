import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import type { DesignMdResponse } from '../types/designmd';

interface ApiErrorShape {
	message?: string;
}

interface UseDesignMdResult {
	data: DesignMdResponse | null;
	isLoading: boolean;
	error: string | null;
	refresh: () => Promise<void>;
	generate: () => Promise<void>;
	regenerate: () => Promise<void>;
	fillGaps: () => Promise<void>;
	download: () => Promise<void>;
}

const DESIGN_MD_ENDPOINT = '/wp-designmd/v1/designmd';

function getErrorMessage( error: unknown ): string {
	if ( error instanceof Error && error.message ) {
		return error.message;
	}

	if ( typeof error === 'object' && error !== null ) {
		const apiError = error as ApiErrorShape;
		if ( apiError.message ) {
			return apiError.message;
		}
	}

	return 'An unexpected error occurred.';
}

export function useDesignMd(): UseDesignMdResult {
	const [ data, setData ] = useState< DesignMdResponse | null >( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const runRequest = useCallback(
		async <TResponse,>( request: () => Promise< TResponse > ): Promise< TResponse | null > => {
			setIsLoading( true );
			setError( null );

			try {
				return await request();
			} catch ( requestError: unknown ) {
				setError( getErrorMessage( requestError ) );
				return null;
			} finally {
				setIsLoading( false );
			}
		},
		[]
	);

	const refresh = useCallback( async () => {
		const response = await runRequest( () =>
			apiFetch< DesignMdResponse >( {
				path: DESIGN_MD_ENDPOINT,
			} )
		);

		if ( response ) {
			setData( response );
		}
	}, [ runRequest ] );

	const generate = useCallback( async () => {
		const response = await runRequest( () =>
			apiFetch< DesignMdResponse >( {
				path: `${ DESIGN_MD_ENDPOINT }/generate`,
				method: 'POST',
			} )
		);

		if ( response ) {
			setData( response );
		}
	}, [ runRequest ] );

	const regenerate = useCallback( async () => {
		const response = await runRequest( () =>
			apiFetch< DesignMdResponse >( {
				path: `${ DESIGN_MD_ENDPOINT }/regenerate`,
				method: 'POST',
			} )
		);

		if ( response ) {
			setData( response );
		}
	}, [ runRequest ] );

	const fillGaps = useCallback( async () => {
		const response = await runRequest( () =>
			apiFetch< DesignMdResponse >( {
				path: `${ DESIGN_MD_ENDPOINT }/fill-gaps`,
				method: 'POST',
			} )
		);

		if ( response ) {
			setData( response );
		}
	}, [ runRequest ] );

	const download = useCallback( async () => {
		const markdown = await runRequest( () =>
			apiFetch< string >( {
				path: `${ DESIGN_MD_ENDPOINT }/download`,
				parse: false,
			} ).then( ( response ) => response.text() )
		);

		if ( ! markdown ) {
			return;
		}

		const blob = new Blob( [ markdown ], { type: 'text/markdown;charset=utf-8' } );
		const objectUrl = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = objectUrl;
		link.download = 'DESIGN.md';
		link.click();
		window.URL.revokeObjectURL( objectUrl );
	}, [ runRequest ] );

	useEffect( () => {
		void refresh();
	}, [ refresh ] );

	return {
		data,
		isLoading,
		error,
		refresh,
		generate,
		regenerate,
		fillGaps,
		download,
	};
}
