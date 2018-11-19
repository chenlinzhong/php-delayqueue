package com.phpdelayqueue;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;
import org.apache.commons.lang3.StringUtils;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.lang.reflect.Type;
import java.nio.charset.Charset;
import java.util.List;

public class DqClient {
    private static Logger logger = LoggerFactory.getLogger(DqClient.class);

    private Gson gson = new GsonBuilder().create();

    private DqConfig dqConfig;

    public DqClient(DqConfig dqConfig) {
        this.dqConfig = dqConfig;
    }

    private <T> DqResponse<T> action(String request_str) {
        logger.info("requst content : {}", request_str);
        String response = SocketUtil.send(dqConfig.getHost(), dqConfig.getPort(),
                request_str.getBytes(Charset.forName("UTF-8")));
        if (StringUtils.isEmpty(response)) {
            return null;
        }
        logger.info("response content : {}", response);

        Type type = new TypeToken<DqResponse<T>>() {
        }.getType();
        return gson.fromJson(response, type);
    }

    public DqResponse<List<String>> add(DqRequestAdd request) {
        request.setCmd("add");
        String requestJson = gson.toJson(request);

        return action(requestJson);
    }

    public DqResponse<String> del(DqRequestBase request) {
        request.setCmd("del");
        String requestJson = gson.toJson(request);
        return action(requestJson);
    }

    public DqResponse<String> get(DqRequestBase request) {
        request.setCmd("get");
        String requestJson = gson.toJson(request);
        return action(requestJson);
    }


    public static void main(String[] args) {
        DqConfig config = new DqConfig();
        config.setHost("");
//        config.setPort(8236);
        config.setPort(6789);

        DqClient dqClient = new DqClient(config);

        DqResponse<String> response;
        DqRequestBase request = new DqRequestBase();
//        request.setId("123456");
//        request.setTopic("fankux");
//        response = dqClient.get(request);
//        logger.info("get response : {}", response.toString());
//
        DqRequestAdd add = new DqRequestAdd();
        add.setTopic("fankux");
        add.setId("123456");
        add.setBody("\\{\"a\" : \"b\"\\}");
        DqResponse<List<String>> addresponse = dqClient.add(add);
        logger.info("add response : {}", addresponse.toString());

        response = dqClient.get(request);
        logger.info("get response : {}", response.toString());

        DqResponse<String> delResponse = dqClient.del(request);
        logger.info("del response : {}", delResponse.toString());
    }
}
