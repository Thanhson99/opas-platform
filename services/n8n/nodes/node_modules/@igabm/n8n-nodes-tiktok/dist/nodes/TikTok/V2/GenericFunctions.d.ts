import type { IDataObject, IExecuteFunctions, IHookFunctions, ILoadOptionsFunctions, INodeParameterResourceLocator, IHttpRequestMethods } from 'n8n-workflow';
export declare function tiktokApiRequest(this: IExecuteFunctions | ILoadOptionsFunctions | IHookFunctions, method: IHttpRequestMethods, resource: string, body?: IDataObject, qs?: IDataObject, fullOutput?: boolean, uri?: string, option?: IDataObject): Promise<any>;
export declare function tiktokApiRequestAllItems(this: IExecuteFunctions | ILoadOptionsFunctions, propertyName: string, method: IHttpRequestMethods, endpoint: string, body?: IDataObject, query?: IDataObject): Promise<IDataObject[]>;
export declare function returnId(contentId: INodeParameterResourceLocator): string;
export declare function returnIdFromUsername(this: IExecuteFunctions, usernameRlc: INodeParameterResourceLocator): Promise<string>;
