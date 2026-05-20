function resolveWebsiteUrl(callbackUrl, language) {
    try {
        return new URL(callbackUrl).origin;
    } catch {
        return language === 'vi' ? 'domain hiện tại của website' : 'the current website domain';
    }
}

function isLocalCallbackUrl(callbackUrl) {
    try {
        const parsedUrl = new URL(callbackUrl);

        return ['localhost', '127.0.0.1', '::1'].includes(parsedUrl.hostname);
    } catch {
        return false;
    }
}

function buildGoogleDocs(language, callbackUrl, websiteUrl) {
    if (language === 'vi') {
        return {
            title: 'Hướng dẫn cấu hình Google OAuth',
            intro: 'Mở đúng project trong **Google Cloud Console**, tạo **OAuth client ID** loại **Web application**, rồi copy chính xác **Client ID**, **Client Secret** và **Redirect URI** theo môi trường hiện tại.',
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
                        'Mở **Google Cloud Console** từ link bên trên.',
                        'Chọn project đang dùng cho website này, hoặc tạo project mới nếu chưa có.',
                        'Vào **APIs & Services** > **Credentials**.',
                        `Nếu cần điền URL website hiện tại ở bước nào đó, dùng: \`${websiteUrl}\`.`,
                    ],
                },
                {
                    title: '2. Chuẩn bị OAuth consent screen nếu Google yêu cầu',
                    items: [
                        'Nếu chưa cấu hình consent screen, bấm **Get started** hoặc vào **OAuth consent screen**.',
                        'Điền **App name** dễ hiểu, ví dụ: **OPAS Login** hoặc **OPAS Google Login**.',
                        'Chọn **User support email** và nhập **Developer contact email**.',
                        'Nếu app đang ở chế độ test, thêm tài khoản test vào danh sách **Test users** để kiểm tra local hoặc staging.',
                    ],
                },
                {
                    title: '3. Tạo OAuth client ID',
                    items: [
                        'Quay lại **APIs & Services** > **Credentials**.',
                        'Bấm **+ Create Credentials** > **OAuth client ID**.',
                        'Ở **Application type**, chọn **Web application**.',
                        'Đặt tên dễ hiểu, ví dụ: **OPAS Web Login**.',
                    ],
                },
                {
                    title: '4. Khai báo đúng website URL và Redirect URI',
                    items: [
                        `Trong **Authorized JavaScript origins**, thêm URL website hiện tại nếu bạn muốn khai báo origin: \`${websiteUrl}\`.`,
                        'Trong **Authorized redirect URIs**, dán đúng URL callback bên dưới:',
                        `\`${callbackUrl}\``,
                        'Sai chỉ 1 ký tự cũng sẽ gây lỗi **redirect_uri_mismatch**.',
                    ],
                },
                {
                    title: '5. Copy giá trị về form này',
                    items: [
                        '**Client ID**: copy từ Google.',
                        '**Client Secret**: copy từ Google.',
                        `**Redirect URI**: dùng đúng \`${callbackUrl}\`.`,
                        '**Button text** có thể để trống hoặc nhập nội dung như "Tiếp tục với Google".',
                    ],
                },
                {
                    title: '6. Nếu đăng nhập chưa chạy',
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
                    label: 'Website URL',
                    text: `Nếu Google yêu cầu **Authorized JavaScript origins** hoặc cần đối chiếu domain hiện tại, dùng \`${websiteUrl}\`.`,
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
        intro: 'Open the correct project in **Google Cloud Console**, create a **Web application OAuth client**, then copy the exact **Client ID**, **Client Secret**, and environment-specific **Redirect URI**.',
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
                    'Open **Google Cloud Console** from the link above.',
                    'Select the project for this website, or create one if needed.',
                    'Go to **APIs & Services** > **Credentials**.',
                    `Use this website URL if Google asks for the current site domain: \`${websiteUrl}\`.`,
                ],
            },
            {
                title: '2. Prepare the OAuth consent screen if Google asks for it',
                items: [
                    'If the consent screen is not configured yet, click **Get started** or open **OAuth consent screen**.',
                    'Set a clear **App name** such as **OPAS Login** or **OPAS Google Login**.',
                    'Select the **User support email** and enter the **Developer contact email**.',
                    'If the app stays in testing mode, add your local or staging accounts as **Test users**.',
                ],
            },
            {
                title: '3. Create the OAuth client ID',
                items: [
                    'Return to **APIs & Services** > **Credentials**.',
                    'Click **+ Create Credentials** > **OAuth client ID**.',
                    'Choose **Web application** as the application type.',
                    'Use a clear name such as **OPAS Web Login**.',
                ],
            },
            {
                title: '4. Register the website URL and Redirect URI',
                items: [
                    `Add the current website URL to **Authorized JavaScript origins** if you want to register the origin: \`${websiteUrl}\`.`,
                    'Paste this exact callback into **Authorized redirect URIs**:',
                    `\`${callbackUrl}\``,
                    'A one-character mismatch will trigger **redirect_uri_mismatch**.',
                ],
            },
            {
                title: '5. Copy the values into this form',
                items: [
                    '**Client ID**: copy it from Google.',
                    '**Client Secret**: copy it from Google.',
                    `**Redirect URI**: use exactly \`${callbackUrl}\`.`,
                    'Optionally set **Button text** like "Continue with Google".',
                ],
            },
            {
                title: '6. If sign-in still fails',
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
                label: 'Website URL',
                text: `If Google asks for the current site origin, use \`${websiteUrl}\`.`,
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

function buildFacebookDocs(language, callbackUrl, websiteUrl) {
    const isLocalCallback = isLocalCallbackUrl(callbackUrl);

    if (language === 'vi') {
        const localWarningStep = isLocalCallback
            ? {
                  title: '0. Trường hợp test local bằng localhost',
                  items: [
                      `Bạn đang test bằng callback local: \`${callbackUrl}\`.`,
                      'Với app còn ở **Development mode**, Meta thường vẫn cho test bằng `http://localhost`, nhưng nhiều trường hợp bạn phải cấu hình **Website platform** trước rồi mới lưu được **Valid OAuth Redirect URI**.',
                      'Nếu bạn vừa tạo app xong mà Meta báo URI chuyển hướng không hợp lệ, hãy làm tiếp đúng các bước thêm **Website** và nhập **Site URL** ở bên dưới rồi quay lại lưu callback.',
                  ],
              }
            : null;

        return {
            title: 'Hướng dẫn cấu hình Facebook Login',
            intro: 'Tạo app mới trong **Meta for Developers**, thêm **Facebook Login**, cấu hình **Website platform**, rồi điền đúng **App ID**, **App Secret** và **Redirect URI** theo môi trường hiện tại.',
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
                ...(localWarningStep ? [localWarningStep] : []),
                {
                    title: '1. Tạo ứng dụng mới từ đầu',
                    items: [
                        'Mở **Meta for Developers** từ link bên trên.',
                        'Bấm **Create App**.',
                        'Nếu Meta hỏi use case, chọn use case phù hợp cho **Facebook Login**. Nếu bạn chỉ cần vào app dashboard nhanh để tự cấu hình tiếp thì có thể chọn **Other**.',
                        'Ở bước loại ứng dụng, với luồng đăng nhập người dùng cuối, chọn loại tương ứng với **Consumer** nếu Meta hiển thị lựa chọn này.',
                        'Nhập **Display Name** dễ hiểu, ví dụ: **OPAS Login**, **OPAS Facebook Login** hoặc **Test Login Localhost**.',
                        'Nhập email liên hệ.',
                        'Nếu không dùng hồ sơ doanh nghiệp, để trống phần **Business** rồi bấm **Create App**.',
                    ],
                },
                {
                    title: '2. Thêm sản phẩm Facebook Login',
                    items: [
                        'Trong màn hình quản trị app, tìm **Facebook Login** rồi bấm **Set up**.',
                        'Nếu Meta hỏi nền tảng, chọn **Web**.',
                        `Chuẩn bị sẵn URL website hiện tại để dùng ở bước nền tảng: \`${websiteUrl}\`.`,
                    ],
                },
                {
                    title: '3. Thêm Website platform trong Settings > Basic',
                    items: [
                        'Ở menu bên trái, vào **Settings** > **Basic**.',
                        'Cuộn xuống cuối trang và bấm **Add Platform**.',
                        'Chọn **Website**.',
                        `Trong ô **Site URL**, nhập đúng URL website hiện tại: \`${websiteUrl}/\`.`,
                        'Bấm **Save Changes**.',
                    ],
                },
                {
                    title: '4. Cấu hình Valid OAuth Redirect URI',
                    items: [
                        'Mở **Facebook Login** > **Settings**.',
                        'Trong ô **Valid OAuth Redirect URIs**, dán đúng URL này:',
                        `\`${callbackUrl}\``,
                        'Nếu trước đó Meta không cho lưu callback local, bước thêm **Website** và **Site URL** ở trên thường là phần còn thiếu.',
                        'Nếu sai, Facebook sẽ từ chối callback.',
                        'Sau đó bấm **Save Changes**.',
                    ],
                },
                {
                    title: '5. Kiểm tra App Domains và chế độ Development',
                    items: [
                        'Trong **Settings** > **Basic**, mục **App Domains** có thể để trống hoặc nhập `localhost` nếu Meta yêu cầu.',
                        'Khi app còn ở **Development mode**, bạn có thể test local bằng `http://localhost` mà chưa cần business verification.',
                        'Không cần đổi sang **Live mode** chỉ để test local.',
                    ],
                },
                {
                    title: '6. Điền App ID và App Secret vào form này',
                    items: [
                        '**Client ID**: dùng **App ID**.',
                        '**Client Secret**: dùng **App Secret** trong **App Settings** > **Basic**.',
                        `**Redirect URI**: dùng đúng \`${callbackUrl}\`.`,
                    ],
                },
                {
                    title: '7. Nếu bấm login mà báo Ứng dụng không hoạt động',
                    items: [
                        'Lỗi này thường có nghĩa là app vẫn ở **Development mode** và tài khoản Facebook đang bấm login không phải **admin/developer/tester** của app.',
                        'Cách nhanh nhất là test bằng chính tài khoản Facebook đã tạo app.',
                        'Nếu muốn test bằng tài khoản khác, vào mục **Roles** của app và thêm tài khoản đó làm **Developer** hoặc **Tester**.',
                        'Tài khoản được mời phải đăng nhập Facebook và **accept** lời mời thì mới test được.',
                    ],
                },
                {
                    title: '8. Lỗi thường gặp',
                    items: [
                        'Meta báo **Đây là URI chuyển hướng không hợp lệ**: thường là bạn chưa thêm **Website** platform hoặc chưa lưu **Site URL** trước khi nhập callback.',
                        'Meta báo **Ứng dụng không hoạt động**: tài khoản test chưa thuộc **Roles** của app hoặc bạn đang đăng nhập bằng tài khoản khác tài khoản tạo app.',
                        'Sai App Secret: secret bị copy thiếu hoặc đã bị thay đổi trong Meta.',
                        'Nếu đã làm đúng các bước mà local callback vẫn bị Meta chặn ở môi trường của bạn, khi đó mới chuyển sang dùng domain/tunnel public rồi để hệ thống sinh callback mới từ `APP_URL`.',
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
                {
                    label: 'Website URL',
                    text: `Nếu Meta hỏi **Site URL**, dùng \`${websiteUrl}/\`. Nếu hỏi **App Domains**, có thể để trống hoặc nhập \`localhost\` khi đang test local.`,
                },
            ],
        };
    }

    const localWarningStep = isLocalCallback
        ? {
              title: '0. Testing locally with localhost',
              items: [
                  `You are testing with the local callback: \`${callbackUrl}\`.`,
                  'In Development mode, Meta often allows `http://localhost`, but many setups only accept the redirect URI after the **Website** platform and **Site URL** have been configured first.',
                  'If Meta rejects the localhost callback at first, continue with the Website platform steps below, then return to the redirect URI field.',
              ],
          }
        : null;

    return {
        title: 'Facebook Login setup guide',
        intro: 'Create the app in **Meta for Developers**, add **Facebook Login**, configure the **Website** platform, then copy the exact **App ID**, **App Secret**, and environment-specific **Redirect URI**.',
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
            ...(localWarningStep ? [localWarningStep] : []),
            {
                title: '1. Create a new app from scratch',
                items: [
                    'Open **Meta for Developers** from the link above.',
                    'Click **Create App**.',
                    'If Meta asks for a use case, choose the one that fits **Facebook Login**. If you just need to reach the dashboard quickly, **Other** is a valid starting point.',
                    'If Meta shows an app type choice for end-user sign-in, choose the type that matches **Consumer**.',
                    'Set a clear **Display Name** such as **OPAS Login**, **OPAS Facebook Login**, or **Test Login Localhost**.',
                    'Enter the contact email.',
                    'Leave the **Business** field empty if you are not using a business portfolio for this local test, then create the app.',
                ],
            },
            {
                title: '2. Add the Facebook Login product',
                items: [
                    'In the app dashboard, find **Facebook Login** and click **Set up**.',
                    'If Meta asks for a platform, choose **Web**.',
                    `Keep the current website URL ready for the platform setup: \`${websiteUrl}\`.`,
                ],
            },
            {
                title: '3. Add the Website platform in Settings > Basic',
                items: [
                    'Open **Settings** > **Basic** in the left menu.',
                    'Scroll down and click **Add Platform**.',
                    'Choose **Website**.',
                    `Set **Site URL** to the current website URL: \`${websiteUrl}/\`.`,
                    'Click **Save Changes**.',
                ],
            },
            {
                title: '4. Configure the Valid OAuth Redirect URI',
                items: [
                    'Open **Facebook Login** > **Settings**.',
                    'Paste this exact value into **Valid OAuth Redirect URIs**:',
                    `\`${callbackUrl}\``,
                    'If Meta refused the localhost callback earlier, the missing Website platform setup above is usually the reason.',
                    'If the URI is wrong, Facebook will reject the callback.',
                    'Save the change.',
                ],
            },
            {
                title: '5. Check App Domains and Development mode',
                items: [
                    'In **Settings** > **Basic**, **App Domains** can stay empty or use `localhost` if Meta explicitly requires a value.',
                    'While the app stays in **Development mode**, local testing with `http://localhost` is acceptable.',
                    'You do not need **Live mode** just to test Facebook Login locally.',
                ],
            },
            {
                title: '6. Copy values into this form',
                items: [
                    '**Client ID**: use the **App ID**.',
                    '**Client Secret**: use the **App Secret** from **App Settings** > **Basic**.',
                    `**Redirect URI**: use exactly \`${callbackUrl}\`.`,
                ],
            },
            {
                title: '7. If login shows "App Not Active"',
                items: [
                    'This usually means the app is still in **Development mode** and the Facebook account used for sign-in is not an **admin/developer/tester** of the app.',
                    'The fastest test path is using the same Facebook account that created the app.',
                    'If you want to test with another account, add that account under the app **Roles** as a **Developer** or **Tester**.',
                    'The invited account must accept the invitation before it can test the login flow.',
                ],
            },
            {
                title: '8. Common issues',
                items: [
                    'Meta says the redirect URI is invalid: the **Website** platform or **Site URL** was not configured before saving the callback.',
                    'Meta says **App Not Active**: the test account is not in the app **Roles**, or you are signed in with a different Facebook account.',
                    'The App Secret is wrong because it was copied incorrectly or changed in Meta.',
                    'Only if Meta still refuses the local callback after all correct steps should you switch to a public dev domain or HTTPS tunnel and let the system regenerate the callback from `APP_URL`.',
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
            {
                label: 'Website URL',
                text: `Use \`${websiteUrl}/\` for **Site URL**. For **App Domains**, leave it empty or use \`localhost\` when testing locally if Meta requires a value.`,
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
    const websiteUrl = resolveWebsiteUrl(callbackUrl, language);

    switch (providerKey) {
        case 'google':
            return buildGoogleDocs(language, callbackUrl, websiteUrl);
        case 'github':
            return buildGithubDocs(language, callbackUrl);
        case 'facebook':
            return buildFacebookDocs(language, callbackUrl, websiteUrl);
        case 'email':
        default:
            return buildEmailDocs(language);
    }
}
