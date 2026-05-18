function buildGoogleDocs(language, callbackUrl) {
    if (language === 'vi') {
        return {
            title: 'Hướng dẫn cấu hình Google OAuth',
            intro: 'Chỉ cần tạo một **OAuth client** trên **Google Cloud Console**, copy **Client ID**, **Client Secret**, rồi dán đúng **Redirect URI** là dùng được.',
            links: [
                {
                    label: 'Mở Google Cloud Console',
                    url: 'https://console.cloud.google.com/apis/credentials',
                },
                {
                    label: 'Xem tài liệu OAuth 2.0 cho Web Server',
                    url: 'https://developers.google.com/identity/protocols/oauth2/web-server',
                },
            ],
            steps: [
                {
                    title: '1. Vào đúng màn hình tạo OAuth',
                    items: [
                        'Mở **Google Cloud Console**.',
                        'Chọn project đang dùng cho website này, hoặc tạo project mới nếu chưa có.',
                        'Vào **APIs & Services** > **Credentials**.',
                    ],
                },
                {
                    title: '2. Tạo OAuth client',
                    items: [
                        'Bấm **Create Credentials** > **OAuth client ID**.',
                        'Nếu Google yêu cầu, cấu hình nhanh **OAuth consent screen** với **App name**, **Support email**, và **Developer contact email**.',
                        'Ở phần **Application type**, chọn **Web application**.',
                        'Đặt tên dễ hiểu, ví dụ: **OPAS Google Login**.',
                    ],
                },
                {
                    title: '3. Nhập Redirect URI đúng 100%',
                    items: [
                        'Trong ô **Authorized redirect URIs**, dán đúng URL này:',
                        `\`${callbackUrl}\``,
                        'Sai chỉ 1 ký tự cũng sẽ gây lỗi **redirect_uri_mismatch**.',
                    ],
                },
                {
                    title: '4. Copy giá trị về form này',
                    items: [
                        '**Client ID**: copy từ Google.',
                        '**Client Secret**: copy từ Google.',
                        `**Redirect URI**: dùng đúng \`${callbackUrl}\`.`,
                        '**Button text** có thể để trống hoặc nhập nội dung như "Tiếp tục với Google".',
                    ],
                },
                {
                    title: '5. Nếu đăng nhập chưa chạy',
                    items: [
                        'Lỗi **redirect_uri_mismatch**: kiểm tra lại **Redirect URI** ở cả 2 bên.',
                        'Lỗi **app not verified** hoặc **access blocked**: thêm tài khoản test vào **OAuth consent screen** nếu app còn ở chế độ test.',
                        'Lỗi **invalid_client**: kiểm tra lại **Client ID** và **Client Secret**.',
                    ],
                },
            ],
            fields: [
                {
                    label: 'Client ID',
                    text: 'Đây là mã public do **Google** cấp. Copy từ đúng **OAuth client** vừa tạo.',
                },
                {
                    label: 'Client Secret',
                    text: 'Đây là secret của ứng dụng. Giá trị này chỉ lưu ở backend. Nếu đổi secret trên **Google Cloud Console**, hãy cập nhật lại ở đây.',
                },
                {
                    label: 'Redirect URI',
                    text: `Google sẽ trả người dùng về URL này sau khi đăng nhập. Giá trị đúng là \`${callbackUrl}\`.`,
                },
                {
                    label: 'Visibility',
                    text: 'Chọn **public** khi muốn hiện nút ngoài trang login. Nếu còn test, có thể để **hidden** trước.',
                },
                {
                    label: 'Sort order',
                    text: 'Số nhỏ hơn sẽ đứng trước. Ví dụ đặt **20** để Google nằm sau Email.',
                },
            ],
        };
    }

    return {
        title: 'Google OAuth setup guide',
        intro: 'Create one **OAuth client** in **Google Cloud Console**, copy **Client ID**, **Client Secret**, and use the exact **Redirect URI** below.',
        links: [
            {
                label: 'Open Google Cloud Console',
                url: 'https://console.cloud.google.com/apis/credentials',
            },
            {
                label: 'Read the OAuth 2.0 Web Server guide',
                url: 'https://developers.google.com/identity/protocols/oauth2/web-server',
            },
        ],
        steps: [
            {
                title: '1. Open the correct Google screen',
                items: [
                    'Open **Google Cloud Console**.',
                    'Select the project for this website, or create one if needed.',
                    'Go to **APIs & Services** > **Credentials**.',
                ],
            },
            {
                title: '2. Create the OAuth client',
                items: [
                    'Click **Create Credentials** > **OAuth client ID**.',
                    'If prompted, complete **OAuth consent screen** with **App name**, **Support email**, and **Developer contact email**.',
                    'Choose **Web application** as the application type.',
                ],
            },
            {
                title: '3. Register the exact Redirect URI',
                items: [
                    'Paste this exact value into **Authorized redirect URIs**:',
                    `\`${callbackUrl}\``,
                    'A one-character mismatch will trigger **redirect_uri_mismatch**.',
                ],
            },
            {
                title: '4. Copy the values into this form',
                items: [
                    '**Client ID**: copy it from Google.',
                    '**Client Secret**: copy it from Google.',
                    `**Redirect URI**: use exactly \`${callbackUrl}\`.`,
                    'Optionally set **Button text** like "Continue with Google".',
                ],
            },
            {
                title: '5. If sign-in still fails',
                items: [
                    '**redirect_uri_mismatch**: the **Redirect URI** values do not match.',
                    '**app not verified** or **access blocked**: add your account as a test user in **OAuth consent screen**.',
                    '**invalid_client**: check the **Client ID** and **Client Secret**.',
                ],
            },
        ],
        fields: [
            {
                label: 'Client ID',
                text: 'The public identifier issued by **Google** for this **OAuth client**.',
            },
            {
                label: 'Client Secret',
                text: 'The private secret for the app. It is stored only on the backend and must be updated here if rotated.',
            },
            {
                label: 'Redirect URI',
                text: `Google sends the user back to this URL after sign-in: \`${callbackUrl}\`.`,
            },
            {
                label: 'Visibility',
                text: 'Use **public** when the button should appear on the login screen. Use **hidden** while testing.',
            },
            {
                label: 'Sort order',
                text: 'Lower numbers appear first. For example, use **20** to place Google after Email.',
            },
        ],
    };
}

function buildGithubDocs(language, callbackUrl) {
    if (language === 'vi') {
        return {
            title: 'Hướng dẫn cấu hình GitHub OAuth',
            intro: 'Tạo một **GitHub OAuth App**, copy **Client ID**, **Client Secret**, rồi dán đúng **Redirect URI** là đủ.',
            links: [
                {
                    label: 'Mở GitHub Developer Settings',
                    url: 'https://github.com/settings/developers',
                },
                {
                    label: 'Xem tài liệu tạo OAuth App',
                    url: 'https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app',
                },
            ],
            steps: [
                {
                    title: '1. Chuẩn bị domain và callback URL',
                    items: [
                        'Mở **GitHub Developer Settings**.',
                        'Vào **OAuth Apps** > **New OAuth App**.',
                        `URL callback của môi trường này là: \`${callbackUrl}\`.`,
                    ],
                },
                {
                    title: '2. Tạo OAuth App',
                    items: [
                        'Điền **Application name** dễ hiểu.',
                        'Điền **Homepage URL** bằng domain hiện tại của website.',
                        'Điền **Authorization callback URL** đúng bằng callback bên dưới.',
                    ],
                },
                {
                    title: '3. Khai báo Callback URL',
                    items: [
                        'Giá trị cần dán:',
                        `\`${callbackUrl}\``,
                        'Nếu khác URL thực tế của môi trường hiện tại, GitHub sẽ không callback đúng.',
                    ],
                },
                {
                    title: '4. Sao chép thông tin vào form',
                    items: [
                        '**Client ID**: lấy từ GitHub.',
                        '**Client Secret**: bấm generate rồi dán vào đây.',
                        `**Redirect URI**: dùng đúng \`${callbackUrl}\`.`,
                    ],
                },
                {
                    title: '5. Lỗi thường gặp',
                    items: [
                        'GitHub báo callback URL không hợp lệ: bạn đã nhập sai URL hoặc sai môi trường.',
                        'Đăng nhập quay về nhưng không hoàn tất: callback URL trong GitHub và trong form không khớp nhau.',
                        'Client Secret không hoạt động: secret đã bị regenerate nhưng chưa cập nhật lại trong form.',
                    ],
                },
            ],
            fields: [
                {
                    label: 'Client ID',
                    text: 'Lấy từ trang OAuth App details trong GitHub Developer Settings.',
                },
                {
                    label: 'Client Secret',
                    text: 'Sau khi generate secret trên GitHub, lưu ngay vào ô này vì bạn sẽ dùng nó để xác thực ở backend.',
                },
                {
                    label: 'Redirect URI',
                    text: `GitHub sẽ trả người dùng về URL này sau đăng nhập: \`${callbackUrl}\`.`,
                },
            ],
        };
    }

    return {
        title: 'GitHub OAuth setup guide',
        intro: 'Create a **GitHub OAuth App**, copy **Client ID** and **Client Secret**, then use the exact **Redirect URI** shown here.',
        links: [
            {
                label: 'Open GitHub Developer Settings',
                url: 'https://github.com/settings/developers',
            },
            {
                label: 'Read the OAuth App guide',
                url: 'https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app',
            },
        ],
        steps: [
            {
                title: '1. Prepare the domain and callback URL',
                items: [
                    'Open **GitHub Developer Settings**.',
                    'Go to **OAuth Apps** > **New OAuth App**.',
                    `The callback URL for this environment is: \`${callbackUrl}\`.`,
                ],
            },
            {
                title: '2. Create an OAuth App',
                items: [
                    'Set a clear **Application name**.',
                    'Use your current site domain as the **Homepage URL**.',
                    'Set **Authorization callback URL** to the exact callback below.',
                ],
            },
            {
                title: '3. Register the callback URL',
                items: [
                    'Paste this exact value:',
                    `\`${callbackUrl}\``,
                    'Do not add extra characters or a URL from another environment.',
                ],
            },
            {
                title: '4. Copy values into this form',
                items: [
                    '**Client ID**: copy it from GitHub.',
                    '**Client Secret**: generate it and store it here.',
                    `**Redirect URI**: use exactly \`${callbackUrl}\`.`,
                ],
            },
            {
                title: '5. Common issues',
                items: [
                    'GitHub rejects the callback URL because the registered URL is wrong or from another environment.',
                    'The sign-in flow returns but cannot complete because the callback URL in GitHub does not match the form value.',
                    'The secret fails because it was regenerated in GitHub and not updated here.',
                ],
            },
        ],
        fields: [
            {
                label: 'Client ID',
                text: 'Copy this from the OAuth App details page in GitHub Developer Settings.',
            },
            {
                label: 'Client Secret',
                text: 'Generate the secret in GitHub and store it here immediately because the backend will use it for OAuth verification.',
            },
            {
                label: 'Redirect URI',
                text: `GitHub returns the user to this callback URL: \`${callbackUrl}\`.`,
            },
        ],
    };
}

function buildFacebookDocs(language, callbackUrl) {
    if (language === 'vi') {
        return {
            title: 'Hướng dẫn cấu hình Facebook Login',
            intro: 'Tạo app trong **Meta for Developers**, bật **Facebook Login**, rồi dán đúng **App ID**, **App Secret** và **Redirect URI**.',
            links: [
                {
                    label: 'Mở Meta for Developers',
                    url: 'https://developers.facebook.com/apps/',
                },
                {
                    label: 'Xem tài liệu Facebook Login',
                    url: 'https://developers.facebook.com/docs/facebook-login/web',
                },
            ],
            steps: [
                {
                    title: '1. Chuẩn bị môi trường cấu hình',
                    items: [
                        'Mở **Meta for Developers**.',
                        'Tạo app hoặc chọn app đang dùng cho website này.',
                        `URL cần dùng cho **Valid OAuth Redirect URI** là: \`${callbackUrl}\`.`,
                    ],
                },
                {
                    title: '2. Tạo app trên Meta',
                    items: [
                        'Vào Meta for Developers và tạo app phù hợp cho Facebook Login.',
                        'Thêm sản phẩm Facebook Login vào app.',
                    ],
                },
                {
                    title: '3. Cấu hình Redirect URI',
                    items: [
                        'Trong **Facebook Login settings**, dán đúng URL này:',
                        `\`${callbackUrl}\``,
                        'Nếu sai, Facebook sẽ từ chối callback.',
                    ],
                },
                {
                    title: '4. Điền App ID và App Secret',
                    items: [
                        '**Client ID**: dùng **App ID**.',
                        '**Client Secret**: dùng **App Secret** trong **App Settings** > **Basic**.',
                        `**Redirect URI**: dùng đúng \`${callbackUrl}\`.`,
                    ],
                },
                {
                    title: '5. Kiểm tra chế độ app',
                    items: [
                        'Nếu app còn ở chế độ development, chỉ những tài khoản được cấp quyền mới test được.',
                        'Khi muốn dùng thật cho người dùng ngoài hệ thống, kiểm tra thêm các bước review/publish của Meta nếu cần.',
                    ],
                },
                {
                    title: '6. Lỗi thường gặp',
                    items: [
                        'URL bị từ chối: Valid OAuth Redirect URI chưa khai báo đúng.',
                        'Đăng nhập không cho tài khoản ngoài dùng: app vẫn đang ở development mode.',
                        'Sai App Secret: secret bị copy thiếu hoặc đã bị thay đổi trong Meta.',
                    ],
                },
            ],
            fields: [
                {
                    label: 'Client ID',
                    text: 'Dùng App ID của ứng dụng Facebook trong Meta for Developers.',
                },
                {
                    label: 'Client Secret',
                    text: 'Dùng App Secret trong App Settings > Basic. Giá trị này chỉ nên lưu ở backend.',
                },
                {
                    label: 'Redirect URI',
                    text: `Meta sẽ redirect về URL này sau khi xác thực: \`${callbackUrl}\`.`,
                },
            ],
        };
    }

    return {
        title: 'Facebook Login setup guide',
        intro: 'Create an app in **Meta for Developers**, enable **Facebook Login**, then copy **App ID**, **App Secret**, and the exact **Redirect URI**.',
        links: [
            {
                label: 'Open Meta for Developers',
                url: 'https://developers.facebook.com/apps/',
            },
            {
                label: 'Read the Facebook Login guide',
                url: 'https://developers.facebook.com/docs/facebook-login/web',
            },
        ],
        steps: [
            {
                title: '1. Prepare the environment',
                items: [
                    'Open **Meta for Developers**.',
                    'Create or select the app for this website.',
                    `The required **Valid OAuth Redirect URI** is: \`${callbackUrl}\`.`,
                ],
            },
            {
                title: '2. Create a Meta app',
                items: [
                    'Open Meta for Developers and create an app for Facebook Login.',
                    'Add the Facebook Login product to the app.',
                ],
            },
            {
                title: '3. Register the redirect URI',
                items: [
                    'Paste this exact value into **Valid OAuth Redirect URI**:',
                    `\`${callbackUrl}\``,
                    'If the URI is wrong, Facebook will reject the callback.',
                ],
            },
            {
                title: '4. Copy values into this form',
                items: [
                    '**Client ID**: use the **App ID**.',
                    '**Client Secret**: use the **App Secret** from **App Settings** > **Basic**.',
                    `**Redirect URI**: use exactly \`${callbackUrl}\`.`,
                ],
            },
            {
                title: '5. Check the app mode',
                items: [
                    'If the app is still in development mode, only allowed accounts can test it.',
                    'Review Meta publishing requirements if this login method will be used by public users.',
                ],
            },
            {
                title: '6. Common issues',
                items: [
                    'The redirect URL is rejected because the Valid OAuth Redirect URI was not registered correctly.',
                    'Only internal testers can sign in because the app is still in development mode.',
                    'The App Secret is wrong because it was copied incorrectly or changed in Meta.',
                ],
            },
        ],
        fields: [
            {
                label: 'Client ID',
                text: 'Use the Facebook App ID from Meta for Developers.',
            },
            {
                label: 'Client Secret',
                text: 'Use the App Secret from App Settings > Basic. This value should remain backend-only.',
            },
            {
                label: 'Redirect URI',
                text: `Meta returns the user to this URL after sign-in: \`${callbackUrl}\`.`,
            },
        ],
    };
}

function buildEmailDocs(language) {
    if (language === 'vi') {
        return {
            title: 'Hướng dẫn đăng nhập Email/Password',
            intro: 'Đây là provider đăng nhập mặc định của hệ thống. Không cần tạo OAuth app bên ngoài, nhưng vẫn nên cấu hình tên hiển thị và vị trí xuất hiện cho rõ ràng.',
            links: [],
            steps: [
                {
                    title: '1. Vai trò của provider này',
                    items: [
                        'Email/Password luôn nên là một phương thức đăng nhập an toàn dự phòng cho hệ thống.',
                        'Provider này nên được giữ làm đường đăng nhập nền để tránh mất hoàn toàn khả năng truy cập khi social login gặp lỗi.',
                    ],
                },
                {
                    title: '2. Các trường không cần nhập',
                    items: [
                        'Không cần Client ID, Client Secret hay Redirect URI.',
                        'Bạn chỉ cần cấu hình các trường hiển thị như display name, icon, sort order và visibility.',
                    ],
                },
                {
                    title: '3. Khuyến nghị cấu hình',
                    items: [
                        'Giữ tên hiển thị rõ ràng, ví dụ: Email and Password hoặc Đăng nhập bằng email.',
                        'Đặt sort order nhỏ nhất nếu muốn phương thức này đứng đầu màn hình đăng nhập.',
                        'Giữ visibility là public nếu đây vẫn là đường đăng nhập mặc định của website.',
                    ],
                },
            ],
            fields: [
                {
                    label: 'Display name',
                    text: 'Tên người dùng nhìn thấy ở màn hình login và trong khu quản trị.',
                },
                {
                    label: 'Sort order',
                    text: 'Số nhỏ hơn sẽ đứng trước trong danh sách đăng nhập. Nên để nhỏ hơn các provider social nếu muốn Email đứng đầu.',
                },
            ],
        };
    }

    return {
        title: 'Email/Password setup guide',
        intro: 'This is the default local sign-in method for the system. No external OAuth app is required, but you should still configure the display settings clearly.',
        links: [],
        steps: [
            {
                title: '1. Understand the role of this provider',
                items: [
                    'Email and password should remain a safe fallback sign-in method for the system.',
                    'Keep it as the baseline sign-in path so the site does not lose all access if a social login provider fails.',
                ],
            },
            {
                title: '2. Fields you do not need here',
                items: [
                    'No Client ID, Client Secret, or Redirect URI is required.',
                    'Only display-related settings such as display name, icon, sort order, and visibility matter here.',
                ],
            },
            {
                title: '3. Recommended setup',
                items: [
                    'Use a clear display name such as Email and Password or Sign in with email.',
                    'Use the lowest sort order if you want this method to appear first on the login screen.',
                    'Keep visibility public if this remains the default sign-in path for the website.',
                ],
            },
        ],
        fields: [
            {
                label: 'Display name',
                text: 'The name users see on the login screen and in the admin panel.',
            },
            {
                label: 'Sort order',
                text: 'Lower numbers appear first. Use a lower value than social providers if Email should stay on top.',
            },
        ],
    };
}

/**
 * Build provider-specific setup copy using callback URLs resolved by the backend.
 *
 * @param {string} providerKey
 * @param {'en'|'vi'} language
 * @param {{ callbackUrl?: string | null }} options
 * @returns {{
 *   title: string,
 *   intro: string,
 *   links: Array<{label: string, url: string}>,
 *   steps: Array<{title: string, items: string[]}>,
 *   fields: Array<{label: string, text: string}>
 * }}
 */
export function getProviderDocs(providerKey, language, options = {}) {
    const callbackUrl = options.callbackUrl || 'Callback URL is not available yet.';

    switch (providerKey) {
        case 'google':
            return buildGoogleDocs(language, callbackUrl);
        case 'github':
            return buildGithubDocs(language, callbackUrl);
        case 'facebook':
            return buildFacebookDocs(language, callbackUrl);
        case 'email':
        default:
            return buildEmailDocs(language);
    }
}
