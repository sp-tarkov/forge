namespace Verifier.Extraction;

/// <summary>
/// A read-only archive stream that reports a length captured once at open and serves reads through a 1 MB buffer.
/// </summary>
public sealed class BufferedArchiveStream : Stream
{
    private readonly BufferedStream _inner;
    private readonly long _length;

    public BufferedArchiveStream(string path)
    {
        FileStream file = File.OpenRead(path);
        _length = file.Length;
        _inner = new BufferedStream(file, 1 << 20);
    }

    public override bool CanRead => true;

    public override bool CanSeek => true;

    public override bool CanWrite => false;

    public override long Length => _length;

    public override long Position
    {
        get => _inner.Position;
        set => _inner.Position = value;
    }

    public override int Read(byte[] buffer, int offset, int count) => _inner.Read(buffer, offset, count);

    public override int ReadByte() => _inner.ReadByte();

    public override long Seek(long offset, SeekOrigin origin) => _inner.Seek(offset, origin);

    public override void Flush()
    {
    }

    public override void SetLength(long value) => throw new NotSupportedException();

    public override void Write(byte[] buffer, int offset, int count) => throw new NotSupportedException();

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            _inner.Dispose();
        }

        base.Dispose(disposing);
    }
}
