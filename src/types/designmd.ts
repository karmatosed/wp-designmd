export interface DesignMdTokens {
	version?: string;
	name?: string;
	colors?: Record<string, string>;
	typography?: Record<string, Record<string, string>>;
	spacing?: Record<string, string>;
	rounded?: Record<string, string>;
	components?: Record<string, Record<string, string>>;
}

export interface LintFinding {
	severity: 'error' | 'warning' | 'info';
	path: string;
	message: string;
}

export interface DesignMdNotice {
	type: 'error' | 'warning' | 'info' | 'success';
	message: string;
}

export interface DesignMdResponse {
	tokens: DesignMdTokens;
	prose: Record<string, string>;
	lint: {
		findings: LintFinding[];
		summary: {
			errors: number;
			warnings: number;
			info: number;
		};
	};
	meta: {
		generated_at?: string;
		theme_slug?: string;
		sparse?: boolean;
	};
	sparse?: boolean;
	notices: DesignMdNotice[];
	raw?: string;
}
