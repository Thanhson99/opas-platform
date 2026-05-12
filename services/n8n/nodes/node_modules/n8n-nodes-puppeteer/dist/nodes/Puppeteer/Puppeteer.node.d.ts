import { type IExecuteFunctions, type ILoadOptionsFunctions, type INodeExecutionData, type INodePropertyOptions, type INodeType, type INodeTypeDescription } from 'n8n-workflow';
export declare const vmResolver: import("@n8n/vm2").Resolver;
export declare class Puppeteer implements INodeType {
    description: INodeTypeDescription;
    methods: {
        loadOptions: {
            getDevices(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
        };
    };
    execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]>;
}
//# sourceMappingURL=Puppeteer.node.d.ts.map