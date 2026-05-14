export default function ErrorState({ text = 'Something went wrong.' }) {
    return (
        <div className="app-feedback app-feedback--error">
            <p>{text}</p>
        </div>
    );
}
