import { type IExecuteFunctions, type ILoadOptionsFunctions, type INodeExecutionData, type INodePropertyOptions, type INodeType, type INodeTypeBaseDescription, type INodeTypeDescription } from 'n8n-workflow';
export declare class TikTokV2 implements INodeType {
    description: INodeTypeDescription;
    constructor(baseDescription: INodeTypeBaseDescription);
    methods: {
        loadOptions: {
            getLanguages(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
        };
    };
    execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]>;
}
