# API Contract Rules

## Standard Response

```json
{
  "success": true,
  "data": {},
  "message": "",
  "errors": null
}
```

Frontend TypeScript shape:

```ts
export type ApiResponse<T> = {
  success: boolean;
  data: T;
  message: string;
  errors?: Record<string, string[]> | null;
};
```

## API Layer Rules

- API access must be isolated from UI.
- Do not call `fetch`, Axios, or transport clients directly inside React components.
- Components call feature hooks or query hooks.
- Hooks may call feature services.
- Hooks must not call transport clients directly.
- Feature services depend on an API client interface, not React rendering.
- Normalize Laravel responses before returning data to UI.
- Map backend DTOs to frontend domain types.
- Map `snake_case` to `camelCase` in service mappers.
- Preserve field errors for forms.
- 401/419 handling belongs in auth/session infrastructure.
- Do not expose secrets, tokens, or privileged config to React state.

## Transport-Agnostic Client Contract

```ts
export type HttpClient = {
  get<T>(url: string, options?: HttpRequestOptions): Promise<T>;
  post<T>(url: string, body?: unknown, options?: HttpRequestOptions): Promise<T>;
  put<T>(url: string, body?: unknown, options?: HttpRequestOptions): Promise<T>;
  delete<T>(url: string, options?: HttpRequestOptions): Promise<T>;
};
```
