package com.phpdelayqueue;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.io.IOException;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.charset.Charset;

class SocketUtil {
    private static Logger logger = LoggerFactory.getLogger(SocketUtil.class);

    private static int CHUNK_SIZE = 4096;

    static String send(String host, Integer port, byte[] content) {
        DataOutputStream out = null;
        try {
            Socket socket = new Socket(host, port);

            out = new DataOutputStream(socket.getOutputStream());

            String headStr = String.format("%04d", content.length);
            byte[] head = headStr.getBytes(Charset.forName("UTF-8"));


            head = ByteBuffer.allocate(4).order(ByteOrder.BIG_ENDIAN).put(head).array();
            content = ByteBuffer.allocate(content.length).order(ByteOrder.BIG_ENDIAN).put(content).array();

            logger.info("head bytes : {}", head);
            logger.info("content bytes : {}", content);

            out.write(head, 0, 4);
            out.write(content);
            out.flush();

            logger.info("content : {}{}", headStr, new String(content));

            DataInputStream in = new DataInputStream(socket.getInputStream());


            if (in.read(head, 0, 4) != 4) {
                logger.error("read response header failed, length not 4");
                return "";
            }
            int responseLen = Integer.parseInt(new String(head, 0, 4));
            logger.info("response header length : {}", responseLen);

            byte[] chunk = new byte[CHUNK_SIZE];
            int total = 0;
            int readlen;
            StringBuilder sb = new StringBuilder();
            while ((readlen = in.read(chunk, 0, CHUNK_SIZE)) > 0) {
                sb.append(new String(chunk, 0, readlen));
                total += readlen;
                if (total >= responseLen) {
                    break;
                }
            }

            return sb.toString();

        } catch (IOException e) {
            logger.error("send content failed", e);
        } finally {
            if (out != null) {
                try {
                    out.close();
                } catch (IOException e) {
                    // do nothing
                }
            }
        }
        return "";
    }

}
