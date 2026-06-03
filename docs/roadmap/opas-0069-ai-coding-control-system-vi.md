# OPAS-0069 [AUTO CODING][TELEGRAM][MULTI-MACHINE] Build AI coding control system

File này dùng để giữ định hướng triển khai cho epic:
`OPAS-0069 [AUTO CODING][TELEGRAM][MULTI-MACHINE] Build AI coding control system`

Mục tiêu của file này:

- giữ định hướng triển khai rõ ràng theo từng phase
- tránh bị lan man khi bắt tay vào code
- biết mỗi phase cần làm gì, chưa làm gì, và xong phase phải đạt được điều gì
- làm tài liệu tham chiếu trước khi tách nhỏ task hoặc bắt đầu code thật

## Tầm nhìn tổng quát của toàn bộ hệ thống

Hệ thống cần tiến tới trạng thái mà:

- người dùng có thể ra lệnh code từ VS Code hoặc từ Telegram
- một máy đang mở workspace phù hợp có thể nhận task và thực thi
- hệ thống hiểu được repo hiện tại đang ở trạng thái nào
- AI có thể hỗ trợ viết code, refactor, review, và follow-up
- hệ thống biết file nào đã thay đổi
- hệ thống chạy được check, test, lint, rule verification
- hệ thống đọc được trạng thái GitHub như issue, PR, CI
- hệ thống biết lúc nào công việc đã đủ điều kiện merge
- hệ thống có lịch sử, log, báo cáo, và về sau có thể mở rộng sang nhiều máy và workflow tự động hơn

Kết quả cuối cùng mong muốn:

- thay vì phải ngồi trực tiếp trong VS Code để tự làm từng bước, người dùng có thể điều phối workflow coding từ local hoặc từ xa
- mỗi task coding đều có trạng thái rõ ràng, có log, có validation, có báo cáo, có theo dõi GitHub
- hệ thống có thể mở rộng dần từ local-first sang Telegram control, multi-machine, dashboard, rồi autonomous workflow
- khi đổi máy hoặc mở session Codex mới, cách hiểu về workflow và mục tiêu vận hành vẫn được giữ ổn định qua docs và persisted state

## Nguyên tắc continuity giữa các máy

Đây là nguyên tắc xuyên suốt của epic này:

- không phụ thuộc vào trí nhớ tạm của một AI session
- không phụ thuộc vào chat history rời rạc
- phải có docs committed để giữ cách hiểu ổn định
- phải có task/run/artifact/report để giữ trạng thái vận hành
- máy khác hoặc session khác phải có thể đọc repo rồi tiếp tục mà không tự bẻ lái yêu cầu

Nói ngắn gọn:

- continuity không phải là “AI nhớ thần kỳ”
- continuity là “cùng một contract được duy trì qua docs + state + workflow”

---

## Phase 1 — Nền tảng local AI coding

### Mục tiêu của phase

Xây nền móng local để một máy đơn có thể:

- nhận task coding
- hiểu workspace và repository context
- gọi AI để hỗ trợ code hoặc refactor
- theo dõi file thay đổi
- chạy validation
- tạo báo cáo kết quả có cấu trúc
- đọc context GitHub như issue, PR, CI ở mức chỉ đọc

### Trọng tâm của phase

Đây là phase nền móng quan trọng nhất vì nó quyết định:

- task được thực thi như thế nào
- state của task được lưu ra sao
- agent local giao tiếp với repo như thế nào
- AI được gọi ra sao
- báo cáo và validation được chuẩn hóa như thế nào

Nếu phase này làm không chặt thì các phase sau như Telegram, multi-machine, dashboard sẽ rất dễ rối.

### Cần làm chi tiết

- Chốt ranh giới của Phase 1
- Xác định rõ Phase 1 chỉ chạy trên một máy local
- Chưa xử lý Telegram
- Chưa xử lý routing nhiều máy
- Chưa làm dashboard phức tạp
- Chưa làm autonomy nâng cao
- Viết note kiến trúc ngắn cho Phase 1 để tránh code trước rồi mới nghĩ kiến trúc sau

- Chọn điểm vào thực thi local
- Quyết định local agent chạy dưới dạng gì
- Có thể là lệnh CLI
- Có thể là command nội bộ
- Có thể là một tiến trình nền nhẹ
- Chốt cách bắt đầu một task local sao cho dễ dùng, dễ debug, dễ mở rộng

- Thiết kế vòng đời của task
- Xác định task là gì
- Xác định run là gì
- Xác định artifact là gì
- Xác định validation result là gì
- Xác định state tối thiểu như pending, running, blocked, failed, completed
- Xác định khi nào task được xem là hoàn tất

- Thiết kế cách lưu trữ dữ liệu Phase 1
- Xác định lưu ở database Laravel, local file, hoặc kết hợp cả hai
- Tối thiểu phải lưu được task, run, repo context, changed files, validation results, report
- Đảm bảo thiết kế này có thể tái sử dụng cho các phase sau

- Xây nhận diện machine local
- Tạo machine id ổn định cho máy hiện tại
- Lưu thông tin tối thiểu như hostname, OS, repo path, workspace path
- Chuẩn bị sẵn nền cho phase multi-machine sau này

- Xây nhận diện repository và workspace
- Detect repo root
- Detect branch hiện tại
- Detect working tree đang sạch hay bẩn
- Detect file staged, modified, untracked
- Detect workspace có hợp lệ để chạy task hay không
- Chặn hoặc cảnh báo nếu repo đang ở trạng thái nguy hiểm

- Xây AI provider abstraction
- Không gọi AI theo kiểu hard-code trực tiếp khắp nơi
- Tạo một interface gọi AI thống nhất
- Chuẩn hóa input cho coding task
- Chuẩn hóa output để dễ parse, dễ retry, dễ report
- Xử lý lỗi cơ bản và retry ở mức hợp lý

- Xây prompt và context assembly
- Xác định dữ liệu nào sẽ đi vào AI request
- Bao gồm task goal
- Bao gồm repo context
- Bao gồm file hoặc module liên quan
- Bao gồm rule liên quan của repo
- Tách rõ system instruction và task-specific instruction
- Tránh việc prompt bị ad hoc mỗi nơi một kiểu

- Xây flow thực thi coding task local
- Nhận task đầu vào
- Load repo context
- Build AI payload
- Gọi AI cho coding hoặc refactor
- Ghi nhận từng step đã chạy
- Lưu output trung gian nếu cần
- Trả về kết quả có cấu trúc thay vì chỉ log text rời rạc

- Xây file change tracking
- Detect file nào thay đổi sau khi task chạy
- Tách rõ modified, staged, untracked nếu cần
- Gắn changed files với task và run hiện tại
- Chuẩn bị dữ liệu này để dùng lại cho review, report, Telegram, dashboard

- Xây diff summary
- Không chỉ biết file nào đổi mà còn cần tóm tắt đổi cái gì
- Có thể theo mức file-level summary trước
- Sau đó mới mở rộng line-level hoặc semantic summary nếu cần
- Mục tiêu là để người dùng review nhanh được

- Xây local validation pipeline
- Xác định các lệnh check tối thiểu cần chạy
- Ví dụ lint, test, composer check, npm check, hoặc rule check riêng
- Chuẩn hóa đầu ra validation
- Biết bước nào pass, bước nào fail
- Lưu validation result vào run hoặc task

- Xây structured reporting
- Tạo báo cáo cho mỗi task
- Báo cáo nên có:
- task input
- machine info
- repo state trước và sau
- changed files
- diff summary
- validation result
- GitHub context
- final status
- blocked reason nếu có
- Báo cáo phải vừa dễ đọc với người, vừa dễ parse với hệ thống

- Xây GitHub read integration
- Đọc được issue liên quan nếu task có mapping
- Đọc được PR trạng thái hiện tại nếu có
- Đọc được CI status nếu có
- Chỉ đọc, chưa cần merge hay ghi lên GitHub ở phase này
- Gắn thông tin đó vào report cuối

- Xây failure handling cho Phase 1
- Xử lý khi repo dirty không phù hợp
- Xử lý khi AI lỗi
- Xử lý khi validation fail
- Xử lý khi thiếu GitHub context
- Xử lý khi task không đủ thông tin để tiếp tục
- Trả blocked state rõ ràng thay vì fail mơ hồ

- Verify toàn bộ flow Phase 1
- Chạy thử end-to-end với một task mẫu
- Xác minh repo detection đúng
- Xác minh changed files tracking đúng
- Xác minh validation pipeline chạy được
- Xác minh report đủ rõ ràng
- Xác minh GitHub read hoạt động ổn

- Viết tài liệu local cho Phase 1
- Cách start local agent
- Cách tạo task
- Cách đọc report
- Các giới hạn hiện tại
- Các assumption đang chấp nhận

### Khi xong Phase 1 phải đạt được gì

- một máy local có thể nhận task coding và thực thi được
- hệ thống hiểu repo hiện tại đang ở đâu, branch nào, có sạch hay không
- AI có thể được gọi thông qua một lớp chuẩn hóa
- file thay đổi được ghi nhận rõ ràng
- validation có thể chạy được và lưu kết quả
- report cuối có cấu trúc, đủ thông tin để review
- GitHub context cơ bản có thể được đọc và đưa vào report

### Kết quả cuối cùng của Phase 1

Sau khi xong phase này, hệ thống đã có “bộ khung local-first” đủ dùng để:

- nhận task
- chạy coding flow
- kiểm tra thay đổi
- chạy validation
- đọc trạng thái GitHub liên quan
- trả báo cáo hoàn chỉnh

Nói ngắn gọn: Phase 1 xong thì hệ thống đã có thể “làm việc được trên một máy”, dù chưa điều khiển từ Telegram và chưa hỗ trợ nhiều máy.

---

## Phase 2 — Workflow engine và validation có kiểm soát

### Mục tiêu của phase

Biến local execution ở Phase 1 từ dạng “chạy được” thành dạng “chạy có quy trình rõ ràng”.

Phase này tập trung vào việc:

- orchestration từng bước
- retry/fix workflow
- completion checklist
- follow-up có kiểm soát
- giảm việc đánh giá thủ công xem task đã thật sự xong chưa

### Trọng tâm của phase

Nếu Phase 1 là nền thực thi, thì Phase 2 là nền kiểm soát chất lượng thực thi.

Hệ thống không chỉ cần chạy task, mà còn phải biết:

- đang ở bước nào
- vì sao fail
- có nên retry hay không
- còn thiếu gì trước khi coi là xong

### Cần làm chi tiết

- Thiết kế workflow state machine
- Xác định từng bước chính trong một coding workflow
- Ví dụ: receive task, inspect repo, prepare context, execute coding, collect changes, run validation, summarize result
- Gắn state transition rõ ràng giữa các bước

- Xây orchestration step-by-step
- Không để task chạy thành một khối mù
- Mỗi step cần biết input, output, status, retry count
- Có khả năng resume hoặc phân tích step nào đang lỗi

- Mở rộng validation pipeline
- Chia validation thành các nhóm rõ ràng
- Ví dụ lint, unit test, build check, repo rule check
- Chuẩn hóa output để workflow engine hiểu được

- Thiết kế retry/fix flow
- Xác định điều kiện nào được phép retry tự động
- Xác định điều kiện nào cần người dùng xác nhận
- Xác định retry bao nhiêu lần là hợp lý
- Tránh loop vô hạn khi validation fail

- Thiết kế completion checklist
- Xác định rõ task hoàn thành cần những điều kiện nào
- Ví dụ không còn validation fail
- repo không ở trạng thái bất thường
- changed files khớp với phạm vi task
- report cuối đầy đủ

- Thêm follow-up interaction
- Khi task thiếu thông tin hoặc có ambiguity, hệ thống cần có cách hỏi lại
- Cho phép task chuyển sang blocked hoặc waiting state
- Có thể resume khi có thêm input

- Tăng tính an toàn của workflow
- Không rerun bừa trên dirty workspace mà không có handling
- Không ghi đè state cũ một cách mơ hồ
- Giữ lịch sử từng lần retry để audit được

### Trạng thái triển khai hiện tại

Phase 2 hiện không còn chỉ là ý tưởng thiết kế. Trong code, workflow engine đã có các khối chính sau:

- workflow step persisted theo từng lần chạy, có step status, attempt history, current step và decision point
- validation pipeline có retry budget, retryable step summary, completion readiness và final checklist
- preflight safety cho dirty workspace và changed-file scope mismatch
- blocked follow-up flow có resume token, input contract, question contract, structured answer payload và resume guard
- final report đã chuẩn hóa các block như workflow, preflight, failure, retry, recommended_action, follow_up

### Service boundaries hiện tại

Sau nhiều lần refactor, `LocalAutoCodingTaskService` hiện đóng vai trò coordinator ở mức cao. Phần lớn logic nặng của Phase 2 đã được tách ra service riêng:

- `AutoCodingExecutionContextService`: build execution context, provider context, normalize policy và scope
- `AutoCodingQueueStateService`: build pending or claimed queue report và queue-state transition
- `AutoCodingExecutionStateService`: tạo run, mark running, finalize blocked or failed or completed execution
- `AutoCodingWorkflowStepRunnerService`: chạy từng workflow step có persisted tracking
- `AutoCodingWorkflowReportService`: build workflow report, preflight report, retry report, failure classification và recommended action
- `AutoCodingFollowUpRequestService`: build preflight follow-up request và normalized provider follow-up request
- `AutoCodingFollowUpWorkflowService`: extract provider follow-up và resolve policy khi resume
- `AutoCodingFollowUpQuestionService`: normalize question contracts
- `AutoCodingFollowUpContractService`: build input contract cho blocked task
- `AutoCodingFollowUpResponseService`: validate resume payload, normalize structured answers và build persisted answer record
- `AutoCodingFollowUpAnswerService`: summarize answer history thành provider-friendly answer map

Điểm quan trọng là source of truth của Phase 2 bây giờ không chỉ nằm ở roadmap này nữa. Khi cần hiểu subsystem hiện tại, nên đọc `LocalAutoCodingTaskService` như entrypoint orchestration, sau đó đọc các service boundary bên trên để thấy trách nhiệm thật sự của từng phần.

### Khi xong Phase 2 phải đạt được gì

- mỗi task có lifecycle rõ ràng
- workflow biết đang chạy bước nào
- validation và retry có quy tắc
- hệ thống biết khi nào task đã đủ điều kiện xem là completed
- follow-up hoặc blocked state được xử lý rõ ràng

### Kết quả cuối cùng của Phase 2

Sau khi xong phase này, local agent không còn là công cụ chỉ “chạy task”, mà trở thành workflow engine có kiểm soát, có trạng thái, có retry logic, và có tiêu chí hoàn thành rõ ràng.

---

## Phase 3 — Điều khiển coding từ Telegram

### Mục tiêu của phase

Cho phép người dùng điều khiển workflow coding từ Telegram thay vì phải ngồi trực tiếp trong VS Code hoặc terminal.

### Trọng tâm của phase

Telegram ở đây không chỉ là nơi nhận text message, mà là một remote control layer cho máy local đang có repo và coding environment.

Quan trọng:

- Telegram không nên dừng ở mức bot slash-command đơn thuần
- Telegram phải dần tiến tới vai trò một conversational remote client cho coding workflow
- nghĩa là ngoài command mode, người dùng còn có thể chat tự nhiên như đang nói chuyện với Codex trong VS Code
- hệ thống phải biết khi nào một tin nhắn là:
- yêu cầu tạo task mới
- câu trả lời follow-up cho task đang blocked
- câu hỏi kiểm tra trạng thái
- hay một chỉ thị coding tự do cần được chuyển thành execution intent có cấu trúc

Nói cách khác:

- mục tiêu của Phase 3 không chỉ là “bấm lệnh từ xa”
- mà là “có thể điều khiển và trao đổi công việc coding từ xa qua Telegram với trải nghiệm gần với chat thật”

### Cần làm chi tiết

- Thiết kế Telegram interaction model
- Chọn command nào là text
- Chọn command nào là menu hoặc button
- Chọn mức thông tin nào nên trả về qua Telegram để vừa đủ rõ, không quá dài

- Tạo Telegram bot integration
- Xử lý xác thực user được phép dùng bot
- Bảo vệ bot khỏi lệnh trái phép

- Xây command handling
- Hỗ trợ các lệnh như start task, review task, run validation, check status, show changed files, show GitHub status
- Chuyển command Telegram thành task nội bộ có cấu trúc

- Xây GitHub-aware task entry
- Cho phép bắt đầu task từ issue key hoặc context GitHub
- Ví dụ:
- `/issue OPAS-0069`
- `/pr 123`
- hoặc `/code --issue OPAS-0069`
- Bot phải đọc được title, body, acceptance intent, PR status, CI status nếu có
- Nếu người dùng chỉ gửi issue key, hệ thống nên có thể dựng task summary cơ bản từ GitHub context thay vì bắt người dùng nhập lại toàn bộ
- Telegram phải trả được GitHub status đủ dùng:
- issue title
- PR hiện tại nếu có
- CI/check status nếu có
- merge blocker chính nếu có

- Xây conversational Telegram mode
- Không phải lúc nào người dùng cũng nên bị buộc dùng slash command
- Cần có một chế độ để người dùng gửi tin nhắn tự nhiên như:
- “xem issue OPAS-0069 rồi fix lỗi telegram callback giúp tôi”
- “review phần thay đổi mới nhất và báo rủi ro”
- “tiếp tục task đang blocked, cứ dùng scope services”
- Bot phải có lớp intent resolution để:
- nhận diện task mới
- map vào task type phù hợp như code, review, validate, status
- hỏi lại khi thiếu thông tin quan trọng
- tạo structured task payload trước khi dispatch
- Với conversational mode, hệ thống phải lưu được session/task linkage tối thiểu để follow-up sau đó không bị rơi context ngay lập tức

- Xây remote chat loop gần với Codex trong VS Code
- Khi người dùng gửi yêu cầu coding tự nhiên qua Telegram, hệ thống không chỉ reply một lần rồi thôi
- Nó phải có khả năng:
- hỏi lại để làm rõ yêu cầu
- báo đã hiểu task theo cách nào
- báo đang chạy bước gì
- báo khi cần confirm hoặc cần input thêm
- trả summary cuối cùng giống một coding assistant thực tế
- Về bản chất, đây là “agent conversation over Telegram”, không chỉ là “command dispatch over Telegram”

- Xây action menus hoặc options
- Cho các workflow phổ biến như review, rerun, check status, confirm action
- Giảm phụ thuộc vào việc nhớ cú pháp lệnh dài
- Tổ chức menu theo tầng thay vì chỉ một hàng nút phẳng
- Menu gốc nên tách ít nhất thành:
- Guide
- Create
- Reports
- Maintenance
- Trong từng menu con, phải có mô tả ngắn giải thích:
- nút đó dùng để làm gì
- khi nào nên bấm
- nếu cần nhập text tiếp theo thì cần nhập theo mẫu nào
- Ví dụ:
- menu Telegram đã được rút gọn về các workflow chính: chat, queue, changes, clear chat, clear all chat
- queue submenu hỗ trợ lọc nhanh theo pending, running, blocked, failed, completed và các action cancel/delete cần thiết
- các menu demo cũ như Create, Reports, Maintenance không còn là surface chính của bot
- Với action không thể hoàn tất chỉ bằng button, bot phải trả về hướng dẫn cụ thể
- Ví dụ nút Create không nên tạo task code mơ hồ nếu chưa có summary
- Thay vào đó nó nên hướng dẫn mẫu `/code <summary>` rõ ràng cho người mới
- dashboard `/help` hiện tại tập trung vào worker snapshot, activity snapshot, active tasks, và action ngắn cho chat/queue/changes/clear
- task-specific keyboard vẫn giữ các report/action liên quan trực tiếp tới task như status, summary, validation, changes, github, cancel

- Kết nối Telegram với local execution system
- Khi người dùng ra lệnh, local agent nhận được đúng task
- Context task không bị mất khi đi từ Telegram vào agent
- Nếu máy local hiện tại là máy duy nhất đang online, Telegram có thể xem nó như active remote workstation mặc định
- Việc chọn giữa nhiều máy khác nhau vẫn có thể để Phase 4 xử lý sâu hơn
- Nhưng trong Phase 3, ít nhất phải có khái niệm:
- máy nào đang connected
- máy nào đang là active receiver của Telegram session hiện tại
- Telegram phải báo rõ task đang được gửi vào máy nào

- Trả kết quả về Telegram
- Báo task đang chạy gì
- Báo changed files
- Báo validation result
- Báo blocked reason nếu có
- Báo task hoàn thành hay chưa
- Báo execution summary đủ rõ để người dùng không cần quay lại VS Code mới hiểu kết quả
- Báo diff summary hoặc changed-file summary đủ tốt để review nhanh trên điện thoại
- Báo next action rõ ràng khi task fail, blocked, hoặc validation chưa pass
- Nếu task gắn issue/PR GitHub thì report nên gộp cả GitHub context liên quan

- Xây report contract riêng cho Telegram
- Không nên đẩy nguyên report nội bộ dài và thô lên Telegram
- Cần thiết kế bản report tối ưu cho chat:
- status headline
- machine
- task summary
- changed files
- validation
- GitHub status
- blocked reason
- next action
- follow-up prompt nếu cần
- Khi report quá dài, bot phải biết chia thành nhiều message hợp lý hoặc ưu tiên phần quan trọng trước

- Thêm safeguard cho action nguy hiểm
- Những hành động rủi ro cao cần confirm
- Tránh việc một tin nhắn mơ hồ gây chạy task sai
- Với conversational mode, intent mơ hồ phải được bot hỏi lại thay vì tự suy diễn mạnh tay
- Các action như delete task, cancel running task, hoặc execute trên workspace dirty nên có confirm flow rõ ràng

### Ghi chú quan trọng về ranh giới Phase 3 và các phase sau

Để tránh roadmap bị mơ hồ, cần chốt rõ:

- Phase 3 nên đạt “conversational remote control trên một connected machine”
- tức là người dùng có thể remote vào một máy đang mở đúng workspace và nói chuyện qua Telegram gần như đang nói với Codex trong VS Code
- Phase 3 chưa nhất thiết phải giải quyết đầy đủ bài toán routing nhiều máy đồng thời
- bài toán nhiều máy, chọn máy tốt nhất, tránh xung đột liên máy vẫn thuộc trọng tâm của Phase 4

Nói ngắn gọn:

- Phase 3 = remote chat + command + report đủ mạnh cho một connected machine
- Phase 4 = multi-machine registration + routing + coordination

### Khi xong Phase 3 phải đạt được gì

- người dùng có thể ra lệnh task cơ bản từ Telegram
- máy local nhận task đúng và chạy được
- Telegram hiển thị được tiến độ và kết quả đủ dùng
- remote coding flow bắt đầu khả thi mà chưa cần dashboard
- Telegram hiện tại đã vượt mức “chưa cần dashboard” và đã có dashboard text-based mức đầu tiên qua `/help`
- người dùng có thể bắt đầu task từ GitHub issue context hoặc từ yêu cầu text tự nhiên
- Telegram có thể dùng như một lớp chat điều khiển coding từ xa chứ không chỉ là menu command
- report cuối đủ rõ để người dùng đọc trên điện thoại vẫn hiểu:
- task đã làm gì
- đổi file nào
- validation ra sao
- GitHub context liên quan đang thế nào
- cần làm gì tiếp theo

### Trạng thái implementation hiện tại

- Telegram bot integration, webhook, allow-list chat/user, và command dispatch đã có
- allow-list chat/user/action và token/webhook hiện đã có thể quản lý từ DB qua trang admin Telegram Bots, không còn chỉ phụ thuộc `.env`
- dashboard `/help`, queue submenu, và action keyboard cho workflow phổ biến đã có
- direct chat-session mode hiện đã có với `/start`, `/chat_status`, `/chat_reset`, `/stop`; alias cũ `/chat_start` và `/chat_stop` vẫn được giữ để tương thích
- chat session được nhớ theo từng Telegram chat và gắn rõ vào transport context của task dưới dạng `chat_session`
- plain-text conversational mode đã có cho code, review, validate, status, summary, changes, github, queue
- tin nhắn mơ hồ hiện đã có clarify flow thay vì tự tạo task ngay
- khi một `issue_key` có nhiều history local cùng loại nhưng reuse hint xung đột, Telegram hiện đã có conflict-aware clarify flow thay vì tự chọn im lặng
- clarify issue-context đó có thể hoàn tất bằng button hoặc bằng cách reply trực tiếp `source task id` trong chat
- action nguy hiểm như cancel, delete, deleteall hiện đã có confirm flow rõ ràng
- task blocked đã có session linkage đủ để follow-up và resume qua Telegram
- conversational mode hiện đã nhận được một số intent text tự nhiên hữu ích hơn:
- `issue OPAS-xxxx ...` hoặc `github issue OPAS-xxxx ...` có thể tạo task code gắn issue key
- `check github`, `xem github`, `check pr`, `check ci`, `check status` có thể map đúng sang report lookup thay vì bị hiểu nhầm thành validation task
- conversational report lookup hiện cũng đã target tốt hơn:
- có thể chỉ đúng task id như `status 12`
- có thể chỉ status slice như `summary failed` hoặc `queue blocked`
- có thể chỉ issue key như `github issue OPAS-xxxx` để lấy task gần nhất gắn issue đó
- có thể chỉ branch như `github branch feature/...`
- có thể chỉ PR number như `check pr 105` miễn là task gần đây đã có persisted GitHub PR URL
- issue-only task creation hiện đã bớt “mù” hơn:
- nếu local đã có task gần nhất cùng `issue_key`, Telegram có thể tái dùng summary gần nhất của issue đó
- đồng thời gắn `issue_context` vào task context để provider prompt có thêm branch/PR/context local liên quan
- Telegram report hiện đã có:
- status
- summary
- changed files
- validation
- GitHub snapshot với headline, PR status, CI status, blocker chính, và next action
- GitHub snapshot hiện tại vẫn là local-read hoặc persisted-report based
- chưa phải GitHub API workflow đầy đủ để đọc live PR sâu, đọc issue body/title đầy đủ, hay thao tác write lên GitHub

### Phần còn thiếu để xem Phase 3 là hoàn chỉnh hơn

- issue-aware intake sâu hơn khi chỉ nhập issue key mà vẫn dựng được title/body/acceptance intent đầy đủ từ GitHub
- GitHub integration sâu hơn cho live PR/CI state thay vì chủ yếu dựa vào local context và persisted report
- report polish thêm cho completion summary theo kiểu chat gần Codex hơn nữa khi task dài hoặc nhiều bước

### Kết quả cuối cùng của Phase 3

Sau khi xong phase này, Telegram đã trở thành một remote interface thực tế cho workflow coding local. Người dùng có thể ở ngoài máy nhưng vẫn theo dõi, ra lệnh, trao đổi theo kiểu chat, và nhận kết quả công việc từ xa trên một máy đang connected.

---

## Phase 4 — Multi-machine execution và routing

### Mục tiêu của phase

Mở rộng hệ thống từ một máy sang nhiều máy có thể nhận task, báo trạng thái, và được chọn đúng lúc để thực thi.

### Trọng tâm của phase

Khi có nhiều máy cùng tồn tại, hệ thống phải biết:

- máy nào đang online
- máy nào đang có repo phù hợp
- máy nào đang bận
- task nào nên đi vào máy nào

### Cần làm chi tiết

- Xây machine registration
- Mỗi máy phải có identity riêng
- Có thể đăng ký kèm hostname, OS, repo list, workspace path

- Xây machine heartbeat
- Máy phải cập nhật trạng thái online, offline, idle, busy
- Có timeout để nhận biết máy stale

- Xây workspace binding
- Biết mỗi machine có repo nào
- branch nào đang active
- context nào sẵn sàng để chạy

- Xây task routing rules
- Chọn máy theo repo phù hợp
- Chọn máy theo availability
- Chọn máy theo capability hoặc context nếu cần

- Xử lý conflict
- Tránh nhiều task cùng chạy trên cùng workspace gây chồng chéo
- Tránh gửi task vào máy không còn context đúng

- Chuẩn bị distributed execution support
- Dù chưa cần quá phức tạp, cần mở đường cho việc giao task qua lại giữa các machine trong tương lai

### Khi xong Phase 4 phải đạt được gì

- nhiều máy có thể được hệ thống nhận diện và quản lý
- mỗi máy có trạng thái rõ ràng
- task có thể được route vào đúng máy phù hợp
- tránh được phần lớn sai sót do chạy nhầm máy hoặc nhầm workspace

### Kết quả cuối cùng của Phase 4

Sau khi xong phase này, hệ thống không còn gắn chặt vào một máy duy nhất. Nó đã có nền điều phối execution theo machine context, là bước bắt buộc trước khi làm việc remote ở quy mô lớn hơn.

---

## Phase 5 — Dashboard, logs, và monitoring

### Mục tiêu của phase

Tạo lớp quan sát tập trung để người dùng không phải phụ thuộc hoàn toàn vào Telegram hoặc terminal logs.

### Trọng tâm của phase

Khi hệ thống bắt đầu có nhiều task, nhiều run, nhiều machine, nếu không có dashboard và log tập trung thì việc theo dõi, debug, audit sẽ rất khó.

### Cần làm chi tiết

- Xây dashboard tổng quan
- Hiển thị task đang chạy
- Hiển thị task đã hoàn tất
- Hiển thị task failed hoặc blocked

- Xây task history
- Cho phép xem lịch sử task theo thời gian
- Xem machine nào đã chạy task nào
- Xem output tổng quát của từng run

- Xây execution logs
- Lưu và hiển thị log theo step
- Biết step nào lỗi, lỗi gì, lúc nào

- Xây artifact review
- Xem changed files
- Xem diff summary
- Xem report cuối

- Xây AI usage tracking
- Theo dõi provider nào được gọi
- Theo dõi tần suất hoặc usage metrics nếu có

- Xây machine monitoring
- Theo dõi machine health
- Theo dõi resource usage cơ bản như CPU, memory, disk nếu phù hợp

- Xây notification layer
- Thông báo task fail
- Thông báo validation fail
- Thông báo task hoàn tất

### Khi xong Phase 5 phải đạt được gì

- người dùng có nơi tập trung để xem toàn bộ task và machine
- việc debug workflow dễ hơn nhiều
- log và report không còn rải rác
- hệ thống bắt đầu có tính auditability thực tế

### Kết quả cuối cùng của Phase 5

Sau khi xong phase này, hệ thống có một lớp quan sát và giám sát tương đối đầy đủ. Đây là nền để vận hành ổn định chứ không chỉ demo được.

---

## Phase 6 — Autonomous AI workflow expansion

### Mục tiêu của phase

Mở rộng hệ thống từ mô hình “điều khiển từ xa” sang mô hình “AI hỗ trợ chủ động hơn”, nhưng vẫn phải có kiểm soát, có log, có khả năng review.

### Trọng tâm của phase

Autonomy ở đây không nên hiểu là để AI tự làm tất cả không giám sát, mà là:

- biết đề xuất plan
- biết review lại kết quả
- biết giữ context dài hạn
- biết gợi ý cách cải thiện
- biết xử lý một số lỗi lặp lại một cách an toàn

### Cần làm chi tiết

- Xây AI planning support
- Cho AI khả năng chia task thành step
- Gợi ý execution plan trước khi chạy

- Xây AI review workflow
- Cho AI review output sau coding
- Phân tích chất lượng, consistency, và risk

- Xây persistent memory và context reuse
- Giữ lại context hữu ích giữa các task
- Không để mỗi task bắt đầu lại từ số 0

- Xây self-improvement suggestions
- Gợi ý cải thiện prompt
- Gợi ý cải thiện validation flow
- Gợi ý cải thiện workflow lặp lại

- Xây bug detection và self-healing có giới hạn
- Nhận biết pattern lỗi lặp
- Cho phép retry hoặc corrective action ở phạm vi an toàn
- Không để AI tự động làm các bước rủi ro cao mà không có kiểm soát

- Chuẩn bị multi-agent expansion
- Tách vai trò planner, reviewer, executor nếu cần
- Giữ log và trách nhiệm rõ ràng cho từng agent role

### Khi xong Phase 6 phải đạt được gì

- hệ thống bắt đầu có khả năng hỗ trợ chủ động hơn
- AI không chỉ phản hồi lệnh mà còn biết plan, review, và gợi ý cải thiện
- autonomy vẫn giữ được tính minh bạch và khả năng kiểm tra lại

### Kết quả cuối cùng của Phase 6

Sau khi xong phase này, hệ thống tiến gần hơn tới một autonomous coding assistant thực thụ, nhưng vẫn giữ nguyên các nguyên tắc an toàn, kiểm soát, log, và validation.

---

## Phase 7 — Verification và release readiness

### Mục tiêu của phase

Chuẩn hóa cổng kiểm tra cuối cùng để biết một task đã thực sự sẵn sàng cho merge và close issue hay chưa.

### Trọng tâm của phase

Nhiều hệ thống chạy được nhưng lại không biết kết luận “xong thật chưa”. Phase này tồn tại để trả lời rõ câu đó.

### Cần làm chi tiết

- Định nghĩa verification gate end-to-end
- Xác định điều kiện tối thiểu trước khi merge
- Xác định điều kiện tối thiểu trước khi close issue

- Kiểm tra repository readiness
- branch state có đúng không
- changed files có hợp lý không
- commit readiness có đủ chưa

- Kiểm tra GitHub readiness
- PR đã ở trạng thái phù hợp chưa
- CI đã pass chưa
- có blocker nào còn tồn tại không

- Kiểm tra completion integrity
- validation đã pass chưa
- report đã đầy đủ chưa
- task có còn blocked state ẩn nào không

- Xây merge readiness summary
- Tóm tắt ngắn gọn vì sao task đã sẵn sàng merge hoặc chưa

- Xây issue closure readiness summary
- Tóm tắt ngắn gọn vì sao task đã sẵn sàng close issue hoặc chưa

### Khi xong Phase 7 phải đạt được gì

- hệ thống có tiêu chí rõ ràng để quyết định merge-ready hay chưa
- hệ thống có tiêu chí rõ ràng để quyết định close issue hay chưa
- giảm việc merge nhầm hoặc close issue khi thực chất task chưa xong

### Kết quả cuối cùng của Phase 7

Sau khi xong phase này, toàn bộ vòng đời từ nhận task đến merge và close issue đã có một cổng xác minh cuối đáng tin cậy. Đây là bước giúp hệ thống đủ trưởng thành để dùng lâu dài thay vì chỉ hỗ trợ coding từng phần.

---

## Tóm tắt đích đến sau khi hoàn tất toàn bộ roadmap

Khi hoàn tất toàn bộ các phase ở mức đủ tốt, hệ thống nên đạt được trạng thái sau:

- có thể nhận task từ local hoặc Telegram
- có thể chọn đúng machine để chạy task
- có thể dùng AI để hỗ trợ coding, review, và follow-up
- có thể theo dõi changed files, validation, và trạng thái GitHub
- có dashboard, log, monitoring, và history
- có khả năng hỗ trợ bán tự động hoặc tự động có kiểm soát
- có cơ chế xác minh cuối cùng trước khi merge và close issue

Nói ngắn gọn:

- Phase 1 tạo nền local
- Phase 2 tạo workflow có kiểm soát
- Phase 3 mở remote control qua Telegram
- Phase 4 mở rộng sang nhiều máy
- Phase 5 thêm khả năng quan sát và vận hành
- Phase 6 thêm khả năng AI chủ động hơn
- Phase 7 khóa lại bằng verification và release readiness

Nếu đi đúng thứ tự này, hệ thống sẽ phát triển chắc hơn, ít rối hơn, và dễ triển khai thực tế hơn so với việc lao ngay vào Telegram hoặc autonomy từ đầu.
